<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();
            $monthStart = Carbon::now()->startOfMonth();
            $monthEnd = Carbon::now()->endOfMonth();
            $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

            // ===== SALES METRICS =====
            $salesMetrics = $this->calculateSalesMetrics($today, $yesterday, $monthStart, $monthEnd, $lastMonthStart, $lastMonthEnd);

            // ===== INVENTORY METRICS =====
            $inventoryMetrics = $this->calculateInventoryMetrics();

            // ===== CUSTOMER METRICS =====
            $customerMetrics = $this->calculateCustomerMetrics($today, $monthStart, $monthEnd);

            // ===== DAMAGED PRODUCTS =====
            $damagedMetrics = $this->calculateDamagedMetrics($monthStart, $monthEnd);

            // ===== RECENT TRANSACTIONS =====
            $recentTransactions = $this->getRecentTransactions();

            // ===== TOP SELLING PRODUCTS =====
            $topProducts = $this->getTopSellingProducts();

            // ===== LOW STOCK ALERTS =====
            $lowStockAlerts = $this->getLowStockAlerts();

            // ===== SALES OVERVIEW (Last 7 days) =====
            $salesChartData = $this->getSalesChartData();

            // ===== CATEGORY DISTRIBUTION =====
            $categoryDistribution = $this->getCategoryDistribution();

            return response()->json([
                'success' => true,
                'data' => [
                    'sales' => $salesMetrics,
                    'inventory' => $inventoryMetrics,
                    'customers' => $customerMetrics,
                    'damaged' => $damagedMetrics,
                    'recent_transactions' => $recentTransactions,
                    'top_products' => $topProducts,
                    'low_stock_alerts' => $lowStockAlerts,
                    'sales_chart' => $salesChartData,
                    'category_distribution' => $categoryDistribution,
                ],
                'timestamp' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Dashboard Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate sales metrics
     */
    private function calculateSalesMetrics($today, $yesterday, $monthStart, $monthEnd, $lastMonthStart, $lastMonthEnd)
    {
        // customer_products holds the purchased items; prices come from products/variants
        $items = DB::table('customer_products as cp')
            ->join('customers as c', 'c.id', '=', 'cp.customer_id')
            ->leftJoin('products as p', 'p.name', '=', 'cp.product_name')
            ->leftJoin('product_variants as pv', function ($join) {
                $join->on('pv.product_id', '=', 'p.id')
                    ->on('pv.unit_label', '=', 'cp.unit');
            })
            ->select([
                'cp.quantity',
                'cp.purchase_date as item_purchase_date',
                'c.purchase_date as customer_purchase_date',
                'p.unit_price as base_unit_price',
                'p.cost_price as base_cost_price',
                'pv.unit_price as variant_unit_price',
                'pv.cost_price as variant_cost_price',
            ])
            ->get();

        $todaySales = 0;
        $yesterdaySales = 0;
        $monthSales = 0;
        $lastMonthSales = 0;
        $totalSales = 0;
        $todayProfit = 0;
        $monthProfit = 0;
        $totalProfit = 0;
        $todayOrders = 0;
        $monthOrders = 0;

        foreach ($items as $item) {
            $purchaseDate = Carbon::parse($item->item_purchase_date ?? $item->customer_purchase_date);
            $quantity = floatval($item->quantity ?? 0);

            $sell = floatval($item->variant_unit_price ?? $item->base_unit_price ?? 0);
            $cost = floatval($item->variant_cost_price ?? $item->base_cost_price ?? 0);
            $revenue = $quantity * $sell;
            $profit = $quantity * ($sell - $cost);

            $totalSales += $revenue;
            $totalProfit += $profit;

            if ($purchaseDate->isSameDay($today)) {
                $todaySales += $revenue;
                $todayProfit += $profit;
                $todayOrders++;
            }

            if ($purchaseDate->isSameDay($yesterday)) {
                $yesterdaySales += $revenue;
            }

            if ($purchaseDate->between($monthStart, $monthEnd)) {
                $monthSales += $revenue;
                $monthProfit += $profit;
                $monthOrders++;
            }

            if ($purchaseDate->between($lastMonthStart, $lastMonthEnd)) {
                $lastMonthSales += $revenue;
            }
        }

        // Calculate trends
        $dailyTrend = $this->calculateTrend($todaySales, $yesterdaySales);
        $monthlyTrend = $this->calculateTrend($monthSales, $lastMonthSales);

        return [
            'total_sales' => round($totalSales, 2),
            'today_sales' => round($todaySales, 2),
            'yesterday_sales' => round($yesterdaySales, 2),
            'month_sales' => round($monthSales, 2),
            'last_month_sales' => round($lastMonthSales, 2),
            'total_profit' => round($totalProfit, 2),
            'today_profit' => round($todayProfit, 2),
            'month_profit' => round($monthProfit, 2),
            'today_orders' => $todayOrders,
            'month_orders' => $monthOrders,
            'daily_trend' => $dailyTrend,
            'monthly_trend' => $monthlyTrend,
            'average_order_value' => $todayOrders > 0 ? round($todaySales / $todayOrders, 2) : 0,
        ];
    }

    /**
     * Calculate inventory metrics
     */
    private function calculateInventoryMetrics()
    {
        $products = DB::table('products')->get();

        $totalItems = 0;
        $totalValue = 0;
        $inStock = 0;
        $lowStock = 0;
        $criticalStock = 0;
        $outOfStock = 0;
        $categories = [];

        foreach ($products as $product) {
            $quantity = intval($product->quantity);
            $unitPrice = floatval($product->unit_price);

            $totalItems += $quantity;
            $totalValue += $quantity * $unitPrice;

            if ($quantity >= 50) {
                $inStock++;
            } elseif ($quantity > 10) {
                $lowStock++;
            } elseif ($quantity > 0) {
                $criticalStock++;
            } else {
                $outOfStock++;
            }

            if ($product->category) {
                $categories[$product->category] = ($categories[$product->category] ?? 0) + 1;
            }
        }

        $totalProducts = $products->count();
        $totalCategories = count($categories);
        $stockHealth = $totalProducts > 0 ? round(($inStock / $totalProducts) * 100, 1) : 0;

        return [
            'total_products' => $totalProducts,
            'total_items' => $totalItems,
            'total_value' => round($totalValue, 2),
            'in_stock' => $inStock,
            'low_stock' => $lowStock,
            'critical_stock' => $criticalStock,
            'out_of_stock' => $outOfStock,
            'total_categories' => $totalCategories,
            'stock_health' => $stockHealth,
            'alerts_count' => $lowStock + $criticalStock + $outOfStock,
        ];
    }

    /**
     * Calculate customer metrics
     */
    private function calculateCustomerMetrics($today, $monthStart, $monthEnd)
    {
        $totalCustomers = DB::table('customers')->count();
        $todayCustomers = DB::table('customers')
            ->whereDate('purchase_date', $today)
            ->count();
        $monthCustomers = DB::table('customers')
            ->whereBetween('purchase_date', [$monthStart, $monthEnd])
            ->count();

        return [
            'total_customers' => $totalCustomers,
            'today_customers' => $todayCustomers,
            'month_customers' => $monthCustomers,
        ];
    }

    /**
     * Calculate damaged products metrics
     */
    private function calculateDamagedMetrics($monthStart, $monthEnd)
    {
        $damagedProducts = DB::table('damaged_products')->get();
        $products = DB::table('products')->get()->keyBy('name');

        $totalDamaged = 0;
        $totalLoss = 0;
        $monthDamaged = 0;
        $monthLoss = 0;

        foreach ($damagedProducts as $damaged) {
            $quantity = intval($damaged->quantity);
            $productData = $products->get($damaged->product_name);
            $unitPrice = $productData ? floatval($productData->unit_price) : 0;
            $loss = $quantity * $unitPrice;

            $totalDamaged += $quantity;
            $totalLoss += $loss;

            $damageDate = Carbon::parse($damaged->date ?? $damaged->created_at);
            if ($damageDate->between($monthStart, $monthEnd)) {
                $monthDamaged += $quantity;
                $monthLoss += $loss;
            }
        }

        return [
            'total_damaged' => $totalDamaged,
            'total_loss' => round($totalLoss, 2),
            'month_damaged' => $monthDamaged,
            'month_loss' => round($monthLoss, 2),
            'total_reports' => $damagedProducts->count(),
        ];
    }

    /**
     * Get recent transactions (last 10)
     */
    private function getRecentTransactions()
    {
        $customers = DB::table('customers')
            ->orderBy('purchase_date', 'desc')
            ->limit(10)
            ->get();

        if ($customers->isEmpty()) {
            return [];
        }

        $customerIds = $customers->pluck('id')->all();

        $totals = DB::table('customer_products as cp')
            ->leftJoin('products as p', 'p.name', '=', 'cp.product_name')
            ->leftJoin('product_variants as pv', function ($join) {
                $join->on('pv.product_id', '=', 'p.id')
                    ->on('pv.unit_label', '=', 'cp.unit');
            })
            ->whereIn('cp.customer_id', $customerIds)
            ->groupBy('cp.customer_id')
            ->selectRaw('cp.customer_id as customer_id, SUM(cp.quantity * COALESCE(pv.unit_price, p.unit_price, 0)) as total_amount, COUNT(*) as items_count')
            ->get()
            ->keyBy('customer_id');

        $transactions = [];
        foreach ($customers as $customer) {
            $row = $totals->get($customer->id);
            $transactions[] = [
                'id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone ?? '',
                'total_amount' => round(floatval($row->total_amount ?? 0), 2),
                'items_count' => intval($row->items_count ?? 0),
                'purchase_date' => $customer->purchase_date,
                'time_ago' => Carbon::parse($customer->purchase_date)->diffForHumans(),
            ];
        }

        return $transactions;
    }

    /**
     * Get top 5 selling products
     */
    private function getTopSellingProducts()
    {
        $rows = DB::table('customer_products as cp')
            ->leftJoin('products as p', 'p.name', '=', 'cp.product_name')
            ->leftJoin('product_variants as pv', function ($join) {
                $join->on('pv.product_id', '=', 'p.id')
                    ->on('pv.unit_label', '=', 'cp.unit');
            })
            ->groupBy('cp.product_name', 'p.category', 'p.quantity')
            ->selectRaw('cp.product_name as name, SUM(cp.quantity) as quantity_sold, SUM(cp.quantity * COALESCE(pv.unit_price, p.unit_price, 0)) as revenue, COUNT(*) as orders, MAX(p.category) as category, MAX(p.quantity) as current_stock')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $rank = 1;
        return $rows->map(function ($row) use (&$rank) {
            return [
                'rank' => $rank++,
                'name' => $row->name,
                'quantity_sold' => floatval($row->quantity_sold ?? 0),
                'revenue' => round(floatval($row->revenue ?? 0), 2),
                'orders' => intval($row->orders ?? 0),
                'category' => $row->category ?? 'General',
                'current_stock' => intval($row->current_stock ?? 0),
            ];
        })->all();
    }

    /**
     * Get low stock alerts
     */
    private function getLowStockAlerts()
    {
        $products = DB::table('products')
            ->where('quantity', '<=', 10)
            ->orderBy('quantity', 'asc')
            ->limit(10)
            ->get();

        $alerts = [];
        foreach ($products as $product) {
            $quantity = intval($product->quantity);
            $severity = 'low';

            if ($quantity === 0) {
                $severity = 'out_of_stock';
            } elseif ($quantity <= 5) {
                $severity = 'critical';
            } else {
                $severity = 'low';
            }

            $alerts[] = [
                'id' => $product->id,
                'name' => $product->name,
                'quantity' => $quantity,
                'category' => $product->category ?? 'General',
                'unit' => $product->unit_of_measurement ?? 'pcs',
                'severity' => $severity,
            ];
        }

        return $alerts;
    }

    /**
     * Get sales overview data (last 7 days)
     */
    private function getSalesChartData()
    {
        $start = Carbon::today()->subDays(6)->startOfDay();
        $end = Carbon::today()->endOfDay();

        $rows = DB::table('customer_products as cp')
            ->join('customers as c', 'c.id', '=', 'cp.customer_id')
            ->leftJoin('products as p', 'p.name', '=', 'cp.product_name')
            ->leftJoin('product_variants as pv', function ($join) {
                $join->on('pv.product_id', '=', 'p.id')
                    ->on('pv.unit_label', '=', 'cp.unit');
            })
            ->whereBetween(DB::raw('COALESCE(cp.purchase_date, c.purchase_date)'), [$start, $end])
            ->groupBy(DB::raw('DATE(COALESCE(cp.purchase_date, c.purchase_date))'))
            ->selectRaw(
                'DATE(COALESCE(cp.purchase_date, c.purchase_date)) as sale_date, ' .
                'SUM(cp.quantity * COALESCE(pv.unit_price, p.unit_price, 0)) as sales, ' .
                'COUNT(*) as orders'
            )
            ->get()
            ->keyBy('sale_date');

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dateStr = $date->format('Y-m-d');
            $row = $rows->get($dateStr);

            $chartData[] = [
                'date' => $dateStr,
                'day' => $date->format('D'),
                'sales' => round(floatval($row->sales ?? 0), 2),
                'orders' => intval($row->orders ?? 0),
            ];
        }

        return $chartData;
    }

    /**
     * Get category distribution
     */
    private function getCategoryDistribution()
    {
        $products = DB::table('products')->get();
        $categories = [];

        foreach ($products as $product) {
            $category = $product->category ?? 'Uncategorized';

            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'count' => 0,
                    'total_stock' => 0,
                    'total_value' => 0,
                ];
            }

            $categories[$category]['count']++;
            $categories[$category]['total_stock'] += intval($product->quantity);
            $categories[$category]['total_value'] += intval($product->quantity) * floatval($product->unit_price);
        }

        $distribution = [];
        foreach ($categories as $categoryName => $data) {
            $distribution[] = [
                'category' => $categoryName,
                'products' => $data['count'],
                'stock' => $data['total_stock'],
                'value' => round($data['total_value'], 2),
            ];
        }

        // Sort by value
        usort($distribution, function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });

        return $distribution;
    }

    /**
     * Calculate percentage trend
     */
    private function calculateTrend($current, $previous)
    {
        if ($previous == 0 || $previous === null) {
            if ($current > 0) {
                return ['value' => 100, 'direction' => 'up'];
            }
            return ['value' => 0, 'direction' => 'neutral'];
        }

        $percentChange = (($current - $previous) / $previous) * 100;

        return [
            'value' => round(abs($percentChange), 1),
            'direction' => $percentChange > 0 ? 'up' : ($percentChange < 0 ? 'down' : 'neutral')
        ];
    }
}

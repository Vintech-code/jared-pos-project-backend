<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

-   [Simple, fast routing engine](https://laravel.com/docs/routing).
-   [Powerful dependency injection container](https://laravel.com/docs/container).
-   Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
-   Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
-   Database agnostic [schema migrations](https://laravel.com/docs/migrations).
-   [Robust background job processing](https://laravel.com/docs/queues).
-   [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

-   **[Vehikl](https://vehikl.com/)**
-   **[Tighten Co.](https://tighten.co)**
-   **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
-   **[64 Robots](https://64robots.com)**
-   **[Cubet Techno Labs](https://cubettech.com)**
-   **[Cyber-Duck](https://cyber-duck.co.uk)**
-   **[Many](https://www.many.co.uk)**
-   **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
-   **[DevSquad](https://devsquad.com)**
-   **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
-   **[OP.GG](https://op.gg)**
-   **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
-   **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# 💼 Sales and Inventory System for JARED Construction Supplies and Trading

This project is a full-stack **Sales and Inventory Management System** designed specifically for **JARED Construction Supplies and Trading**, a community-based business located in Purok 3, Barangay Tablon. The system helps streamline inventory tracking, automate sales, generate reports, and manage product damages — all with a user-friendly interface.

---

## 🔧 Technologies Used

### 🖥️ Frontend

-   React 19 + TypeScript
-   Vite
-   Tailwind CSS
-   Axios
-   React Router
-   TanStack React Query

### 🛠️ Backend

-   Laravel 10 (REST API)
-   PHP 8.1+
-   Laravel Sanctum (auth)
-   MySQL / MariaDB
-   Composer

---

## ⚙️ System Features

-   🛒 **Add, Deduct, Update, and Manage Products**
-   📦 **Real-Time Inventory Tracking**
-   📉 **Sales & Revenue Reports**
-   🗂️ **Track Damaged Items**
-   🧾 **Customer Purchase Records**
-   🔒 **User Authentication (Basic)**
-   📊 **Dashboard Metrics and Trends**
-   📁 **Export Reports (via backend)**

---

## 🗂️ Repository Structure

This project is separated into two repositories:

| Part     | Repo Type | Description                             |
| -------- | --------- | --------------------------------------- |
| Frontend | React App | User interface, Axios for API requests  |
| Backend  | Laravel   | API routes, controllers, DB interaction |

### 1. Clone the Repository

---

## ⚙️ Backend Setup (Laravel)

1. Clone/download the backend folder
2. Run the following:

```bash
git clone https://github.com/Vintech-code/jared-pos-project-backend.git
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

---

## ⚙️ Frontend Setup (React - Vite - Typescript)

1. Clone/download the frontend folder
2. Run the following:

```bash
git clone https://github.com/Vintech-code/jared-pos-project.git
cd jared-pos-project
npm install
npm run dev
```

---

## 📥 Setting Up the Project (From ZIP or Local Folder)

> If you're not cloning from GitHub and are using the ZIP folder downloaded from Google Drive, follow these steps:

1. **Extract the ZIP file** to a location on your PC (e.g. `C:/BSIT-2D Development of Sales and Inventory for JARED Construction Supplies and Trading/`).
2. You should see a folder structure like this:

```
BSIT-2D Development of Sales and Inventory for JARED Construction Supplies and Trading/
├── frontend/Inventory/
├── backend/inventorybackend/
└── README.md
```

---

## ⚙️ Backend Setup (Laravel)

1. Open a terminal or command prompt inside `backend/inventorybackend`.
2. Run the following:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

3. Start the development server:

```bash
php artisan serve
```

> ✅ Ensure your `.env` has the correct DB and `APP_URL` settings:

```env
APP_URL=http://localhost:8000
```

---

## 🎨 Frontend Setup (React)

1. Open a new terminal inside `frontend/Inventory`.
2. Run the following:

```bash
npm install
```

3. Start the development server:

```bash
npm run dev
```

---

## 🔗 Default Local URLs

-   Backend API: `http://localhost:8000/api`
-   Frontend: `http://localhost:5173`

---

## 🗄️ Database Setup

-   Import the included `.sql` file into MySQL (e.g. via phpMyAdmin or MySQL CLI)
-   Or let Laravel create the tables and seed sample data:

```bash
php artisan migrate --seed
```

---

## 🌐 RESTful API

This system uses RESTful architecture. All requests are made via HTTP using Axios from the frontend to the Laravel backend.

### Example API Endpoints:

| Method | Endpoint             | Description       |
| ------ | -------------------- | ----------------- |
| GET    | `/api/products`      | Get all products  |
| POST   | `/api/products`      | Add a new product |
| PUT    | `/api/products/{id}` | Update a product  |
| DELETE | `/api/products/{id}` | Delete a product  |

---

## ⚠️ Notes

-   Ensure MySQL is running and Laravel `.env` is configured correctly.
-   Enable CORS in Laravel if needed.
-   Use Postman or Axios for testing API calls.

---

## 🧳 Deployment Notes

-   Local deployment uses **Apache (XAMPP)** or Laravel’s built-in server.
-   React runs on Vite dev server by default (`localhost:5173`).
-   For production, separate builds can be created using `npm run build`.

---

## 🤝 Credits

Developed by:  
**JARED Construction Supplies and Trading**  
Barangay Tablon, Purok 3

---

## 📬 Contact & Support

For support or suggestions, please contact the project maintainers or refer to the included documentation in the Google Drive folder.

---

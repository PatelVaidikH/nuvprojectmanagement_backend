### Project Setup Guide

#### Requirements  
 
- **PHP** (Recommended: PHP 8.3)  
- **Composer** (Dependency Manager for PHP)  
- **Laravel** (Installed via Composer)  
- **MySQL** (Database)  
- **WAMP/XAMPP/MAMP** (For local server setup)   

---

### Installation Steps  

##### 1. Clone the Repository  
```bash
git clone https://github.com/PatelVaidikH/nuvprojectmanagement_backend.git
```
Navigate into the project directory:  
```bash
cd nuvprojectmanagement_backend
```

##### 2. Install Dependencies  
Run the following command to install Laravel and its dependencies:  
```bash
composer install
```
If you don't have a `vendor` folder, this command will generate it.

##### 3. Configure `.env` File  
Update the `.env` file with your database details:  
```plaintext
DB_CONNECTION=mysql  
DB_HOST=127.0.0.1  
DB_PORT=3306  
DB_DATABASE=nuvprojectmanagement  
DB_USERNAME=root  
DB_PASSWORD=your_password  
```

##### 4. Run Database Migrations  
```bash
php artisan migrate
```

##### 5. Serve the Application  
Start the development server:  
```bash
php artisan serve
```
Your Laravel project should now be running at:  
http://127.0.0.1:8000 

# NAS Scanner Admin Application

## Tech Stack
1. PHP 8.3
2. MySQL 8.2
3. Apache or Nginx webserver with Laravel config
4. Laravel 12

## Installation Steps

1. git clone https://github.com/Ashar-khursheed/pim_flow_laravel.git
2. cd pim_flow_laravel
3. Run 'composer update'.
4. Make new .env file from .env.example. Setup db connections in .env
5. Run `php artisan key:generate` to generate key
6. Run `php artisan migrate`.
7. Setup webserver to point to `public` folder
8. Open the app in web browser.


## PHP Settings
1. Change php.ini creds:
  a. post_max_size = 512M.
  b. upload_max_filesize = 512M
  c. memory_limit = 1024M
  d. max_execution_time = 360
  e. max_input_time = 120
  f. max_input_vars = 10000
2. Create a folder named "omr" in the storage folder with write permission.

## Mail Settings
1. Set mail credentials (SMTP) in .env file.

## Set the following command in supervisor controller
### For production environment:
`php artisan queue:work --queue=default --tries=2`

### For development environment:
`php artisan queue:listen --queue=default --tries=2 --timeout=43200`

# Job default: It is being used for any common tasks based on top priority
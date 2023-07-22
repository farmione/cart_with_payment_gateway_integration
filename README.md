
## How to run the source code
- Git clone repository.
- cd repo folder
- cp .env.example `.env`
- open `.env` and update DB_DATABASE credential
- run `composer install`
- run `npm install`
- run `php artisan key:generate`
- run `php artisan migrate:fresh --seed`
- run `php artisan serve`
- run `npm run dev`
- open `.env` and put the `PAYPAL_CLIENT_ID`,`PAYPAL_CLIENT_SECRET`,`PAYPAL_CURRENCY` values which you got from sandbox account

## Login Credential
- Default email: test@example.com
- Default password: password

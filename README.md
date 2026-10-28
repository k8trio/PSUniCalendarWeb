# Pangasinan State University Event Calendar

PSU UniCalendar is a web-based system for publishing university events and managing event requests. It gives the public a central calendar of approved events and gives university offices and the Planning Office tools to submit, review, and manage those events.

## How the system works

1. An authenticated university office submits an event request with its schedule, venue, audience, and other event details.
2. The Planning Office reviews the request and can approve it, reject it, or return it for revision.
3. Approved events appear on the public calendar. Visitors can filter events by the submitting university office and open an event to see its details.
4. Office users can view their request statuses and the approved events available to them. Planning Office users can oversee requests, offices, venues, and calendar activity.

Event venue or campus information is shown as event detail; the calendar office filter identifies the office that submitted the event.

## User roles

- **Public visitor:** Can view approved events on the read-only public calendar and filter them by university office.
- **Office (Admin):** Can sign in to submit and manage event requests for their office, track request status, and use the calendar features permitted for their account.
- **Planning Office (Superadmin):** Can review event requests, approve or reject them, return requests for revision, and manage venues, office information, notifications, and system-wide event oversight.

Access to staff features is protected by authentication and role-based route permissions.

## Technology

- Laravel and PHP
- Blade, HTML, CSS, and JavaScript
- FullCalendar for calendar display
- The database configured for the application

## Local development

1. Install PHP/Composer and Node.js/npm dependencies:

   ```sh
   composer install
   npm install
   ```

2. Create a local environment file from `.env.example`, configure the database, and generate the application key:

   ```sh
   cp .env.example .env
   php artisan key:generate
   ```

3. Run database migrations and build the frontend assets:

   ```sh
   php artisan migrate
   npm run build
   ```

4. Start the Laravel development server:

   ```sh
   php artisan serve
   ```

Run the automated test suite with:

```sh
php artisan test
```

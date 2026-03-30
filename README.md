# J&T Rider Remittance & Payroll System (CodeIgniter 4)

A web app for tracking rider delivery performance, daily cash remittances, and monthly payroll with PDF receipts/payslips.

## Stack
- Backend: CodeIgniter 4 (PHP)
- Frontend: HTML, CSS, JavaScript (Bootstrap 5)
- Database: MySQL
- PDF: Dompdf

## Implemented Modules
- Rider profile management (admin)
- Daily delivery tracking and auto `Total Due` computation (successful deliveries x commission rate)
- Remittance management with denomination entry (25c, 1, 5, 10, 20, 50, 100, 500, 1000)
- Auto-calculated remittance totals and variance status (`SHORT`, `OVER`, `BALANCED`)
- Rider portal for monthly running salary and delivery stats
- Monthly payroll generation with downloadable PDF payslip
- Downloadable PDF remittance receipt

## Setup
1. Create MySQL database:
   - `jt_rider_payroll`
2. Configure DB credentials in `.env` if needed.
3. Run migrations:
   - `php spark migrate`
4. (Optional) Seed sample riders:
   - `php spark db:seed DemoDataSeeder`
5. Start local server:
   - `php spark serve`
6. Open:
   - `http://localhost/J%26T%20Remmittance%20and%20Payroll%20App/public/`

## Main Routes
- `GET /admin` Admin dashboard
- `POST /admin/riders` Create rider
- `POST /admin/deliveries` Save daily deliveries
- `GET /admin/remittance/{deliveryRecordId}` Remittance entry page
- `POST /admin/remittance/{deliveryRecordId}` Save remittance
- `GET /admin/remittance/pdf/{id}` Download remittance PDF
- `POST /admin/payroll/generate` Generate monthly payroll
- `GET /admin/payroll/{id}/pdf` Download payroll PDF
- `GET /rider/{id}` Rider portal

## Notes
- Payroll net pay currently deducts only remittance shortages from gross earnings.
- If there is an over-remittance, it is reflected in variance but not added to net pay.

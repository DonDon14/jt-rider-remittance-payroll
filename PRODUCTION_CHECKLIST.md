# Production Deployment Checklist

1. Keep `.env` out of version control and store only environment-specific secrets on the server.
2. Set `CI_ENVIRONMENT=production`.
3. Set the real `app.baseURL` to the public HTTPS URL.
4. Configure production database credentials with a non-root user and a strong password.
5. Set an `encryption.key`.
6. Point the web server document root to `public/`.
7. Run `php spark migrate --all` on the production database.
8. Set `auth.bootstrapAdminPassword` before the first production migration, then rotate or remove it after first login.
9. Set `auth.apiTokenTtlHours` so mobile bearer tokens expire on a defined schedule.
10. Set `auth.adminRecoveryKey` so admin forgot-password recovery works.
11. Verify `writable/` is writable by the web server but not publicly exposed.
12. Confirm HTTPS is terminated correctly so secure cookies and redirects work.
13. Disable any demo or seed data on production.
14. Run `php vendor/bin/phpunit` before deploy and verify rider approval, remittance save, payroll generation, and password reset flows before release.
15. Run API smoke checks from the server:
    - Read-only:
      `ADMIN_USER=<admin_user> ADMIN_PASS=<admin_pass> ./deploy-tools/smoke_api.sh --base-url "http://127.0.0.1/api"`
    - With write checks:
      `ADMIN_USER=<admin_user> ADMIN_PASS=<admin_pass> DELETE_DELIVERY_RECORD_ID=<id> PAYROLL_RIDER_ID=<id> PAYROLL_MONTH=YYYY-MM PAYROLL_CUTOFF=FIRST RELEASE_PAYROLL_ID=<id> ./deploy-tools/smoke_api.sh --base-url "http://127.0.0.1/api" --write`


# Production Deployment Checklist

1. Remove the tracked `.env` from the repository and keep only environment-specific secrets on the server.
2. Set `CI_ENVIRONMENT=production`.
3. Set the real `app.baseURL` to the public HTTPS URL.
4. Configure production database credentials with a non-root user and a strong password.
5. Set an `encryption.key`.
6. Point the web server document root to `public/`.
7. Run `php spark migrate --all` on the production database.
8. Set `auth.bootstrapAdminPassword` before the first production migration, then rotate or remove it after first login.
9. Verify `writable/` is writable by the web server but not publicly exposed.
10. Confirm HTTPS is terminated correctly so secure cookies and redirects work.
11. Disable any demo or seed data on production.
12. Run `php vendor/bin/phpunit` before deploy and verify rider approval, remittance save, payroll generation, and password reset flows before release.


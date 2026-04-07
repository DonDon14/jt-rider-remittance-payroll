# J&T Rider Mobile

This is a Flutter rider app scaffold wired to the mobile API provided by the main CodeIgniter app.

## Current scope

- Login with rider credentials
- Rider dashboard summary
- Delivery submission with remittance account selection
- Submission history
- Payroll history
- Payroll receipt confirmation
- Announcements
- Logout

## Before running

1. Install Flutter on the machine that will run the mobile app.
2. From this folder, run `flutter pub get`.
3. Update the API base URL in `lib/config/app_config.dart`.
4. Make sure the PHP app is running and reachable from the phone/emulator.

### Local network note

If the backend runs on your laptop and you test on a real phone, do not use `localhost`.
Use your machine LAN IP, for example:

```dart
static const String apiBaseUrl = 'http://192.168.1.10:8080/api';
```

## Suggested first run

```bash
flutter pub get
flutter run
```

## Rider test flow

1. Login with a rider account.
2. Open `Requests` and submit a delivery request.
3. Check `Announcements`.
4. Check `Payroll` and confirm receipt on a released payroll.
5. Logout and log back in.

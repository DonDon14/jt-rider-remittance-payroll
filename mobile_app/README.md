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

## Local run

1. Install Flutter on the machine that will run the mobile app.
2. From this folder, run `flutter pub get`.
3. Start the backend so the phone or emulator can reach it.
4. Run with the correct API URL for your environment.

```bash
flutter pub get
flutter run --dart-define=API_BASE_URL=http://192.168.18.74:8081/api
```

## Release build

1. Copy `android/key.properties.example` to `android/key.properties`.
2. Create the keystore file referenced by `storeFile`.
3. Build with your production API URL.

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://your-domain.example/api
```

If `android/key.properties` does not exist, the app falls back to the debug signing config so local testing still works.

## Rider test flow

1. Login with a rider account.
2. Open `Requests` and submit a delivery request.
3. Check `Announcements`.
4. Check `Payroll` and confirm receipt on a released payroll.
5. Logout and log back in.

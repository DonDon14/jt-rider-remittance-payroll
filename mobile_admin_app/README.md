# J&T Admin Mobile

This is a Flutter admin app scaffold wired to the admin mobile API provided by the main CodeIgniter app.

## Current scope

- Admin login/logout
- Pending submission queue
- Approve or reject rider submissions
- Pending remittance monitoring
- Shortage ledger view
- Payroll list with release action

## Local run

1. Install Flutter.
2. From this folder, run `flutter pub get`.
3. Start the backend so the device can reach it.
4. Run with the correct API URL.

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

If `android/key.properties` does not exist, the app falls back to the debug signing config for local testing.

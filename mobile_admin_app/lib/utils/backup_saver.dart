import 'backup_saver_io.dart' if (dart.library.html) 'backup_saver_web.dart' as impl;

Future<String> saveBackupFile({
  required List<int> bytes,
  required String fileName,
}) {
  return impl.saveBackupFile(bytes: bytes, fileName: fileName);
}


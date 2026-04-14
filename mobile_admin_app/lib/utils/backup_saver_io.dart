import 'dart:io';

import 'package:path_provider/path_provider.dart';

Future<String> saveBackupFile({
  required List<int> bytes,
  required String fileName,
}) async {
  Directory? targetDir;

  if (Platform.isAndroid) {
    final candidates = <Directory>[
      Directory('/storage/emulated/0/Documents'),
      Directory('/storage/emulated/0/Download'),
    ];

    for (final directory in candidates) {
      try {
        if (!await directory.exists()) {
          await directory.create(recursive: true);
        }
        targetDir = directory;
        break;
      } catch (_) {
        continue;
      }
    }

    targetDir ??= await getExternalStorageDirectory();
  } else {
    targetDir = await getApplicationDocumentsDirectory();
  }

  if (targetDir == null) {
    throw const FileSystemException('Unable to resolve a writable folder for backup download.');
  }

  if (!await targetDir.exists()) {
    await targetDir.create(recursive: true);
  }

  final file = File('${targetDir.path}/$fileName');
  await file.writeAsBytes(bytes, flush: true);
  return 'Backup saved to ${file.path}';
}


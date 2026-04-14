import 'dart:io';

import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';

import '../services/api_client.dart';

Future<String> savePayslip({
  required List<int> bytes,
  required String fileName,
}) async {
  Directory? targetDir;

  if (Platform.isAndroid) {
    final storageStatus = await Permission.storage.request();
    if (!storageStatus.isGranted && !storageStatus.isLimited) {
      await Permission.manageExternalStorage.request();
    }

    final candidates = <Directory>[
      Directory('/storage/emulated/0/Documents'),
      Directory('/storage/emulated/0/Download'),
    ];

    for (final dir in candidates) {
      try {
        if (!await dir.exists()) {
          await dir.create(recursive: true);
        }
        targetDir = dir;
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
    throw ApiException('Unable to resolve a writable folder for payslip download.');
  }

  if (!await targetDir.exists()) {
    await targetDir.create(recursive: true);
  }

  final file = File('${targetDir.path}/$fileName');
  await file.writeAsBytes(bytes, flush: true);
  return file.path;
}

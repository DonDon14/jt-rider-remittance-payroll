import 'payslip_saver_io.dart' if (dart.library.html) 'payslip_saver_web.dart' as impl;

Future<String> savePayslip({
  required List<int> bytes,
  required String fileName,
}) {
  return impl.savePayslip(bytes: bytes, fileName: fileName);
}

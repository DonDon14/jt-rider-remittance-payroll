import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';

import '../controllers/session_controller.dart';
import '../services/api_client.dart';

class RiderHomeScreen extends StatefulWidget {
  const RiderHomeScreen({
    super.key,
    required this.sessionController,
    required this.apiClient,
  });

  final SessionController sessionController;
  final ApiClient apiClient;

  @override
  State<RiderHomeScreen> createState() => _RiderHomeScreenState();
}

class _RiderHomeScreenState extends State<RiderHomeScreen> {
  int _currentIndex = 0;

  @override
  Widget build(BuildContext context) {
    final session = widget.sessionController.session!;
    final pages = [
      _OverviewTab(apiClient: widget.apiClient, token: session.token),
      _RequestsTab(apiClient: widget.apiClient, token: session.token),
      _PayrollTab(apiClient: widget.apiClient, token: session.token),
      _AnnouncementsTab(apiClient: widget.apiClient, token: session.token),
      _SettingsTab(sessionController: widget.sessionController),
    ];

    return Scaffold(
      appBar: AppBar(
        title: Text(session.riderName ?? 'Rider'),
      ),
      body: pages[_currentIndex],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (value) => setState(() => _currentIndex = value),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), label: 'Overview'),
          NavigationDestination(icon: Icon(Icons.receipt_long_outlined), label: 'Requests'),
          NavigationDestination(icon: Icon(Icons.payments_outlined), label: 'Payroll'),
          NavigationDestination(icon: Icon(Icons.campaign_outlined), label: 'News'),
          NavigationDestination(icon: Icon(Icons.settings_outlined), label: 'Settings'),
        ],
      ),
    );
  }
}

class _OverviewTab extends StatefulWidget {
  const _OverviewTab({
    required this.apiClient,
    required this.token,
  });

  final ApiClient apiClient;
  final String token;

  @override
  State<_OverviewTab> createState() => _OverviewTabState();
}

class _OverviewTabState extends State<_OverviewTab> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.apiClient.getAuthed('rider/dashboard', widget.token);
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: () async {
        setState(() {
          _future = widget.apiClient.getAuthed('rider/dashboard', widget.token);
        });
        await _future;
      },
      child: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorState(
              message: snapshot.error.toString(),
              onRetry: () {
                setState(() {
                  _future = widget.apiClient.getAuthed('rider/dashboard', widget.token);
                });
              },
            );
          }

          final data = snapshot.data!['data'] as Map<String, dynamic>;
          final stats = data['stats'] as Map<String, dynamic>? ?? <String, dynamic>{};
          final paydayPreview = data['payday_preview'] as Map<String, dynamic>? ?? <String, dynamic>{};
          final runningSalary = _currency(stats['current_payable'] ?? stats['running_salary']);
          final cards = <MapEntry<String, String>>[
            MapEntry('Allocated', '${stats['allocated'] ?? 0}'),
            MapEntry('Successful', '${stats['successful'] ?? 0}'),
            MapEntry('Failed', '${stats['failed'] ?? 0}'),
            MapEntry('Total Earned This Month', _currency(stats['month_earnings'])),
            MapEntry('Already In Payroll', _currency(stats['paid_earnings'])),
            MapEntry('Expected Remittance', _currency(stats['expected_remittance'])),
            MapEntry('Total Remitted', _currency(stats['total_remitted'])),
            MapEntry('Shortage Deductions', _currency(stats['shortage_deductions'])),
            MapEntry('Repayments', _currency(stats['shortage_repayments'])),
            MapEntry('Projected Net', _currency(stats['projected_net'])),
          ];

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text('Month: ${data['month'] ?? ''}', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              Card(
                color: Theme.of(context).colorScheme.primaryContainer,
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Current Payable',
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          color: Theme.of(context).colorScheme.onPrimaryContainer,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        runningSalary,
                        style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                          color: Theme.of(context).colorScheme.onPrimaryContainer,
                          fontWeight: FontWeight.w800,
                          fontSize: 34,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Coverage: ${paydayPreview['start_date'] ?? data['month'] ?? ''} to ${paydayPreview['effective_end_date'] ?? paydayPreview['end_date'] ?? ''}'
                        '${(paydayPreview['payout_date'] ?? '').toString().isNotEmpty ? '\nExpected payout day: ${paydayPreview['payout_date']}' : ''}'
                        '\nPaid salary stays in Payroll history.',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Theme.of(context).colorScheme.onPrimaryContainer,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 8),
              ...cards.map(
                (card) => Card(
                  child: ListTile(title: Text(card.key), trailing: Text(card.value)),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _RequestsTab extends StatefulWidget {
  const _RequestsTab({
    required this.apiClient,
    required this.token,
  });

  final ApiClient apiClient;
  final String token;

  @override
  State<_RequestsTab> createState() => _RequestsTabState();
}

class _RequestsTabState extends State<_RequestsTab> {
  late Future<Map<String, dynamic>> _submissionsFuture;
  late Future<Map<String, dynamic>> _accountsFuture;

  final _dateController = TextEditingController();
  final _allocatedController = TextEditingController();
  final _successfulController = TextEditingController();
  final _expectedController = TextEditingController();
  final _notesController = TextEditingController();
  final ValueNotifier<int> _failedPreview = ValueNotifier<int>(0);

  int? _selectedAccountId;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _submissionsFuture = widget.apiClient.getAuthed('rider/submissions', widget.token, query: const {'page': '1', 'per_page': '20'});
    _accountsFuture = widget.apiClient.getAuthed('rider/remittance-accounts', widget.token);
    _dateController.text = _formatDate(DateTime.now());
    _allocatedController.addListener(_recalculateFailedPreview);
    _successfulController.addListener(_recalculateFailedPreview);
  }

  @override
  void dispose() {
    _allocatedController.removeListener(_recalculateFailedPreview);
    _successfulController.removeListener(_recalculateFailedPreview);
    _dateController.dispose();
    _allocatedController.dispose();
    _successfulController.dispose();
    _expectedController.dispose();
    _notesController.dispose();
    _failedPreview.dispose();
    super.dispose();
  }

  void _recalculateFailedPreview() {
    final allocated = int.tryParse(_allocatedController.text.trim()) ?? 0;
    final successful = int.tryParse(_successfulController.text.trim()) ?? 0;
    _failedPreview.value = (allocated - successful) < 0 ? 0 : (allocated - successful);
  }

  Future<void> _submitRequest() async {
    if (_selectedAccountId == null) {
      _showMessage('Select a remittance account first.');
      return;
    }

    setState(() => _submitting = true);
    try {
      await widget.apiClient.postAuthed(
        'rider/delivery-submissions',
        widget.token,
        body: {
          'delivery_date': _dateController.text.trim(),
          'allocated_parcels': _allocatedController.text.trim(),
          'successful_deliveries': _successfulController.text.trim(),
          'expected_remittance': _expectedController.text.trim(),
          'remittance_account_id': _selectedAccountId.toString(),
          'notes': _notesController.text.trim(),
        },
      );

      _showMessage('Delivery request submitted.');
      _dateController.clear();
      _allocatedController.clear();
      _successfulController.clear();
      _expectedController.clear();
      _notesController.clear();

      setState(() {
        _submissionsFuture = widget.apiClient.getAuthed('rider/submissions', widget.token, query: const {'page': '1', 'per_page': '20'});
      });
    } on ApiException catch (error) {
      _showMessage(error.message);
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _pickDate() async {
    final current = _tryParseDate(_dateController.text) ?? DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime.now().subtract(const Duration(days: 45)),
      lastDate: DateTime.now().add(const Duration(days: 14)),
      helpText: 'Choose delivery date',
    );

    if (selected != null) {
      setState(() {
        _dateController.text = _formatDate(selected);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: Future.wait([_accountsFuture, _submissionsFuture]).then((value) => {'accounts': value[0], 'submissions': value[1]}),
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(
            message: snapshot.error.toString(),
            onRetry: () {
              setState(() {
                _accountsFuture = widget.apiClient.getAuthed('rider/remittance-accounts', widget.token);
                _submissionsFuture = widget.apiClient.getAuthed('rider/submissions', widget.token, query: const {'page': '1', 'per_page': '20'});
              });
            },
          );
        }

        final data = snapshot.data!;
        final accountItems = ((data['accounts'] as Map<String, dynamic>)['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ?? <dynamic>[];
        final submissionItems = ((data['submissions'] as Map<String, dynamic>)['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ?? <dynamic>[];

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('Submit Delivery Request', style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _dateController,
                      readOnly: true,
                      onTap: _pickDate,
                      decoration: const InputDecoration(
                        labelText: 'Delivery Date',
                        prefixIcon: Icon(Icons.calendar_month_outlined),
                        suffixIcon: Icon(Icons.expand_more_rounded),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(controller: _allocatedController, decoration: const InputDecoration(labelText: 'Allocated Parcels'), keyboardType: TextInputType.number),
                    const SizedBox(height: 12),
                    TextField(controller: _successfulController, decoration: const InputDecoration(labelText: 'Successful Deliveries'), keyboardType: TextInputType.number),
                    const SizedBox(height: 12),
                    ValueListenableBuilder<int>(
                      valueListenable: _failedPreview,
                      builder: (context, failed, _) => InputDecorator(
                        decoration: const InputDecoration(
                          labelText: 'Undelivered Parcels',
                          helperText: 'Auto-calculated as allocated minus successful.',
                        ),
                        child: Text(
                          '$failed',
                          style: Theme.of(context).textTheme.bodyLarge,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(controller: _expectedController, decoration: const InputDecoration(labelText: 'Expected Remittance'), keyboardType: const TextInputType.numberWithOptions(decimal: true)),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      key: ValueKey(_selectedAccountId),
                      initialValue: _selectedAccountId,
                      decoration: const InputDecoration(labelText: 'Remittance Account'),
                      items: accountItems.map((item) => DropdownMenuItem<int>(value: item['id'] as int, child: Text(item['account_name'] as String? ?? ''))).toList(),
                      onChanged: (value) => setState(() => _selectedAccountId = value),
                    ),
                    const SizedBox(height: 12),
                    TextField(controller: _notesController, maxLines: 3, decoration: const InputDecoration(labelText: 'Notes')),
                    const SizedBox(height: 16),
                    FilledButton(onPressed: _submitting ? null : _submitRequest, child: Text(_submitting ? 'Submitting...' : 'Submit Request')),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text('Recent Requests', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            ...submissionItems.map(
              (item) => Card(
                child: ListTile(
                  title: Text(item['delivery_date'] as String? ?? ''),
                  subtitle: Text('Status: ${item['status'] ?? ''}\nExpected: ${_currency(item['expected_remittance'])}'),
                  isThreeLine: true,
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _PayrollTab extends StatefulWidget {
  const _PayrollTab({
    required this.apiClient,
    required this.token,
  });

  final ApiClient apiClient;
  final String token;

  @override
  State<_PayrollTab> createState() => _PayrollTabState();
}

class _PayrollTabState extends State<_PayrollTab> {
  late Future<Map<String, dynamic>> _future;
  bool _downloading = false;

  @override
  void initState() {
    super.initState();
    _future = widget.apiClient.getAuthed('rider/payrolls', widget.token, query: const {'page': '1', 'per_page': '20'});
  }

  Future<void> _confirmReceipt(int id) async {
    try {
      await widget.apiClient.postAuthed('rider/payroll/$id/confirm', widget.token, body: {'received_notes': 'Confirmed from mobile app.'});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Payroll receipt confirmed.')));
      setState(() {
        _future = widget.apiClient.getAuthed('rider/payrolls', widget.token, query: const {'page': '1', 'per_page': '20'});
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  Future<void> _downloadPayslip(Map<String, dynamic> item) async {
    final id = item['id'] as int?;
    if (id == null) {
      return;
    }

    setState(() => _downloading = true);
    try {
      final bytes = await widget.apiClient.downloadPayrollPdf(widget.token, id);
      final startDate = (item['start_date'] ?? '').toString().replaceAll('/', '-');
      final fileName = 'payslip-$id-$startDate.pdf';

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
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Payslip saved to ${file.path}')),
      );
    } on ApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _downloading = false);
      }
    }
  }

  Future<void> _viewPayslip(Map<String, dynamic> item) async {
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Payslip Details'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Coverage: ${item['start_date']} to ${item['end_date']}'),
              Text('Gross earnings: ${_currency(item['gross_earnings'])}'),
              Text('Net pay: ${_currency(item['net_pay'])}'),
              Text('Status: ${item['payroll_status'] ?? ''}'),
              if ((item['payout_method'] ?? '').toString().isNotEmpty)
                Text('Payout method: ${item['payout_method']}'),
              if ((item['payout_reference'] ?? '').toString().isNotEmpty)
                Text('Reference: ${item['payout_reference']}'),
              if ((item['released_at'] ?? '').toString().isNotEmpty)
                Text('Released: ${item['released_at']}'),
              if ((item['received_at'] ?? '').toString().isNotEmpty)
                Text('Received: ${item['received_at']}'),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Close'),
          ),
          FilledButton(
            onPressed: _downloading ? null : () async => _downloadPayslip(item),
            child: Text(_downloading ? 'Downloading...' : 'Download PDF'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(
            message: snapshot.error.toString(),
            onRetry: () {
              setState(() {
                _future = widget.apiClient.getAuthed('rider/payrolls', widget.token, query: const {'page': '1', 'per_page': '20'});
              });
            },
          );
        }

        final items = ((snapshot.data!['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ?? <dynamic>[]);
        return ListView(
          padding: const EdgeInsets.all(16),
          children: items.map((item) {
            final status = item['payroll_status'] as String? ?? '';
            return Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${item['start_date']} to ${item['end_date']}'),
                    const SizedBox(height: 8),
                    Text('Net Pay: ${_currency(item['net_pay'])}'),
                    Text('Status: $status'),
                    if ((item['payout_method'] ?? '').toString().isNotEmpty) Text('Method: ${item['payout_method']}'),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        OutlinedButton(
                          onPressed: () => _viewPayslip(item as Map<String, dynamic>),
                          child: const Text('View Payslip'),
                        ),
                        FilledButton.tonal(
                          onPressed: _downloading ? null : () => _downloadPayslip(item as Map<String, dynamic>),
                          child: Text(_downloading ? 'Downloading...' : 'Download PDF'),
                        ),
                      ],
                    ),
                    if (status == 'RELEASED') ...[
                      const SizedBox(height: 12),
                      FilledButton(onPressed: () => _confirmReceipt(item['id'] as int), child: const Text('Confirm Received')),
                    ],
                  ],
                ),
              ),
            );
          }).toList(),
        );
      },
    );
  }
}

class _AnnouncementsTab extends StatefulWidget {
  const _AnnouncementsTab({
    required this.apiClient,
    required this.token,
  });

  final ApiClient apiClient;
  final String token;

  @override
  State<_AnnouncementsTab> createState() => _AnnouncementsTabState();
}

class _AnnouncementsTabState extends State<_AnnouncementsTab> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.apiClient.getAuthed('rider/announcements', widget.token);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(
            message: snapshot.error.toString(),
            onRetry: () {
              setState(() {
                _future = widget.apiClient.getAuthed('rider/announcements', widget.token);
              });
            },
          );
        }

        final items = ((snapshot.data!['data'] as Map<String, dynamic>)['items'] as List<dynamic>? ?? <dynamic>[]);
        return ListView(
          padding: const EdgeInsets.all(16),
          children: items
              .map((item) => Card(child: ListTile(title: Text(item['title'] as String? ?? ''), subtitle: Text(item['message'] as String? ?? ''))))
              .toList(),
        );
      },
    );
  }
}

class _SettingsTab extends StatelessWidget {
  const _SettingsTab({
    required this.sessionController,
  });

  final SessionController sessionController;

  @override
  Widget build(BuildContext context) {
    final session = sessionController.session;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(child: ListTile(title: Text(session?.riderName ?? ''), subtitle: Text(session?.username ?? ''))),
        const SizedBox(height: 12),
        FilledButton.tonal(onPressed: () => sessionController.logout(), child: const Text('Logout')),
      ],
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

String _currency(dynamic value) {
  final amount = double.tryParse(value.toString()) ?? 0;
  return 'PHP ${amount.toStringAsFixed(2)}';
}




DateTime? _tryParseDate(String value) {
  try {
    return DateTime.parse(value);
  } catch (_) {
    return null;
  }
}

String _formatDate(DateTime date) {
  final year = date.year.toString().padLeft(4, '0');
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return "$year-$month-$day";
}



import 'package:flutter/material.dart';

import '../controllers/session_controller.dart';
import '../services/api_client.dart';
import '../utils/backup_saver.dart';

class AdminHomeScreen extends StatefulWidget {
  const AdminHomeScreen({
    super.key,
    required this.sessionController,
    required this.apiClient,
  });

  final SessionController sessionController;
  final ApiClient apiClient;

  @override
  State<AdminHomeScreen> createState() => _AdminHomeScreenState();
}

class _AdminHomeScreenState extends State<AdminHomeScreen> {
  bool _isLoading = true;
  bool _isLoggingOut = false;
  bool _isDownloadingBackup = false;
  String? _error;

  List<Map<String, dynamic>> _pendingSubmissions = const [];
  Map<String, dynamic> _pendingSubmissionMeta = const {};
  List<Map<String, dynamic>> _pendingRemittances = const [];
  Map<String, dynamic> _pendingRemittanceMeta = const {};
  List<Map<String, dynamic>> _shortages = const [];
  Map<String, dynamic> _shortageMeta = const {};
  List<Map<String, dynamic>> _payrolls = const [];
  Map<String, dynamic> _payrollMeta = const {};
  String _payrollStatusFilter = '';

  String get _token => widget.sessionController.session?.token ?? '';

  @override
  void initState() {
    super.initState();
    _loadAll();
  }

  Future<void> _loadAll() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final submissions = await widget.apiClient.fetchPendingSubmissions(_token);
      final remittances = await widget.apiClient.fetchPendingRemittances(_token);
      final shortages = await widget.apiClient.fetchShortages(_token);
      final payrolls = await widget.apiClient.fetchPayrolls(_token, status: _payrollStatusFilter);

      if (!mounted) {
        return;
      }

      setState(() {
        _pendingSubmissions = submissions.items;
        _pendingSubmissionMeta = submissions.meta;
        _pendingRemittances = remittances.items;
        _pendingRemittanceMeta = remittances.meta;
        _shortages = shortages.items;
        _shortageMeta = shortages.meta;
        _payrolls = payrolls.items;
        _payrollMeta = payrolls.meta;
      });
    } on ApiException catch (error) {
      setState(() {
        _error = error.message;
      });
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _reloadPayrolls() async {
    try {
      final payrolls = await widget.apiClient.fetchPayrolls(_token, status: _payrollStatusFilter);
      if (!mounted) {
        return;
      }
      setState(() {
        _payrolls = payrolls.items;
        _payrollMeta = payrolls.meta;
      });
    } on ApiException catch (error) {
      _showMessage(error.message, isError: true);
    }
  }

  Future<void> _approveSubmission(Map<String, dynamic> item) async {
    try {
      final detail = await widget.apiClient.fetchPendingSubmissionDetail(_token, item['id'] as int);
      if (!mounted) {
        return;
      }

      final submission = detail['submission'] as Map<String, dynamic>? ?? <String, dynamic>{};
      final rider = submission['rider'] as Map<String, dynamic>? ?? <String, dynamic>{};
      final account = submission['remittance_account'] as Map<String, dynamic>? ?? <String, dynamic>{};
      final controller = TextEditingController(text: ((submission['commission_rate'] ?? 10)).toString());

      final confirmed = await showDialog<double>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Review Submission'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${rider['name'] ?? 'Unknown Rider'} (${rider['rider_code'] ?? ''})', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 10),
                Text('Delivery date: ${submission['delivery_date'] ?? ''}'),
                Text('Allocated parcels: ${submission['allocated_parcels'] ?? 0}'),
                Text('Successful deliveries: ${submission['successful_deliveries'] ?? 0}'),
                Text('Failed deliveries: ${submission['failed_deliveries'] ?? 0}'),
                Text('Expected remittance: ${_money(submission['expected_remittance'])}'),
                Text('Remittance account: ${account['name'] ?? 'Not set'} ${_asText(account['number']).isNotEmpty ? '- ${account['number']}' : ''}'),
                if ((submission['notes'] ?? '').toString().trim().isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Text('Rider notes', style: Theme.of(context).textTheme.labelLarge),
                  const SizedBox(height: 4),
                  Text((submission['notes'] ?? '').toString()),
                ],
                const SizedBox(height: 16),
                TextField(
                  controller: controller,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(
                    labelText: 'Commission Rate',
                    helperText: 'Final earning per successful delivery.',
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(double.tryParse(controller.text.trim())),
              child: const Text('Approve and Collect'),
            ),
          ],
        ),
      );

      if (confirmed == null || confirmed <= 0) {
        return;
      }

      final response = await widget.apiClient.approveSubmission(_token, item['id'] as int, confirmed);
      final deliveryRecordId = _safeInt(response['delivery_record_id']);
      _showMessage((response['message'] ?? 'Submission approved.').toString());
      await _loadAll();
      if (deliveryRecordId != null && mounted) {
        await _openRemittanceCollector(deliveryRecordId);
      }
    } on ApiException catch (error) {
      _showMessage(error.message, isError: true);
    }
  }

  Future<void> _rejectSubmission(Map<String, dynamic> item) async {
    final controller = TextEditingController();
    final confirmed = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Reject Submission'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(
            labelText: 'Rejection Note',
            helperText: 'Optional explanation for the rider.',
          ),
          maxLines: 3,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton.tonal(
            onPressed: () => Navigator.of(context).pop(controller.text.trim()),
            child: const Text('Reject'),
          ),
        ],
      ),
    );

    if (confirmed == null) {
      return;
    }

    try {
      await widget.apiClient.rejectSubmission(_token, item['id'] as int, confirmed);
      _showMessage('Submission rejected.');
      await _loadAll();
    } on ApiException catch (error) {
      _showMessage(error.message, isError: true);
    }
  }

  Future<void> _openRemittanceCollector(int deliveryRecordId) async {
    TextEditingController? cashController;
    TextEditingController? gcashController;
    TextEditingController? referenceController;
    TextEditingController? notesController;
    final denominationControllers = <String, TextEditingController>{};
    ValueNotifier<double>? denominationCash;
    ValueNotifier<double>? thisEntryTotal;
    ValueNotifier<double>? projectedGrandTotal;
    ValueNotifier<String>? projectedStatus;

    try {
      final detail = await widget.apiClient.fetchRemittanceDetail(_token, deliveryRecordId);
      if (!mounted) {
        return;
      }

      final delivery = detail['delivery'] as Map<String, dynamic>? ?? <String, dynamic>{};
      final rider = delivery['rider'] as Map<String, dynamic>? ?? <String, dynamic>{};
      final account = delivery['remittance_account'] as Map<String, dynamic>? ?? <String, dynamic>{};
      final currentRemittance = detail['remittance'] as Map<String, dynamic>? ?? <String, dynamic>{};
      final entryRows = (detail['entries'] as List<dynamic>? ?? const <dynamic>[])
          .whereType<Map<String, dynamic>>()
          .toList();
      final denominationValues = _normalizeDenominations(detail['denominations']);
      final orderedDenominations = denominationValues.entries.toList()
        ..sort((a, b) => a.value.compareTo(b.value));

      cashController = TextEditingController();
      gcashController = TextEditingController();
      referenceController = TextEditingController();
      notesController = TextEditingController();
      for (final entry in orderedDenominations) {
        denominationControllers[entry.key] = TextEditingController();
      }

      final currentTotal = _safeDouble(currentRemittance['total_remitted']);
      final expectedTotal = _safeDouble(delivery['expected_remittance']);
      denominationCash = ValueNotifier<double>(0);
      thisEntryTotal = ValueNotifier<double>(0);
      projectedGrandTotal = ValueNotifier<double>(currentTotal);
      projectedStatus = ValueNotifier<String>(
        (currentRemittance['variance_type'] ?? 'PENDING').toString(),
      );

      var syncingCashFromDenoms = false;
      void recalculate() {
        var denomCash = 0.0;
        for (final entry in orderedDenominations) {
          final count = int.tryParse(denominationControllers[entry.key]!.text.trim()) ?? 0;
          if (count > 0) {
            denomCash += entry.value * count;
          }
        }
        denomCash = double.parse(denomCash.toStringAsFixed(2));
        denominationCash!.value = denomCash;

        if (denomCash > 0) {
          final denomText = denomCash.toStringAsFixed(2);
          if (cashController!.text.trim() != denomText) {
            syncingCashFromDenoms = true;
            cashController.value = cashController.value.copyWith(
              text: denomText,
              selection: TextSelection.collapsed(offset: denomText.length),
              composing: TextRange.empty,
            );
            syncingCashFromDenoms = false;
          }
        }

        final cash = _safeDouble(cashController!.text);
        final gcash = _safeDouble(gcashController!.text);
        final entryTotal = double.parse((cash + gcash).toStringAsFixed(2));
        final projectedTotal = double.parse((currentTotal + entryTotal).toStringAsFixed(2));
        thisEntryTotal!.value = entryTotal;
        projectedGrandTotal!.value = projectedTotal;
        projectedStatus!.value = _projectedVarianceType(projectedTotal, expectedTotal);
      }

      for (final controller in denominationControllers.values) {
        controller.addListener(recalculate);
      }
      gcashController.addListener(recalculate);
      cashController.addListener(() {
        if (!syncingCashFromDenoms) {
          recalculate();
        }
      });
      recalculate();

      final result = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (context) => Padding(
          padding: EdgeInsets.only(
            left: 16,
            right: 16,
            top: 8,
            bottom: MediaQuery.of(context).viewInsets.bottom + 24,
          ),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Remittance Entry', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 12),
                Text('${rider['name'] ?? ''} (${rider['rider_code'] ?? ''})'),
                Text('Delivery date: ${delivery['delivery_date'] ?? ''}'),
                Text('Expected remittance: ${_money(delivery['expected_remittance'])}'),
                Text('Current total: ${_money(currentRemittance['total_remitted'])}'),
                Text('Account: ${account['name'] ?? 'Not set'} ${_asText(account['number']).isNotEmpty ? '- ${account['number']}' : ''}'),
                const SizedBox(height: 16),
                ValueListenableBuilder<double>(
                  valueListenable: denominationCash!,
                  builder: (context, value, _) => Text(
                    'Cash from denominations: ${_money(value)}',
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                const SizedBox(height: 6),
                ValueListenableBuilder<double>(
                  valueListenable: thisEntryTotal!,
                  builder: (context, value, _) => Text('This entry total: ${_money(value)}'),
                ),
                const SizedBox(height: 6),
                ValueListenableBuilder<double>(
                  valueListenable: projectedGrandTotal!,
                  builder: (context, value, _) => Text('Projected grand total: ${_money(value)}'),
                ),
                const SizedBox(height: 6),
                ValueListenableBuilder<String>(
                  valueListenable: projectedStatus!,
                  builder: (context, value, _) => Row(
                    children: [
                      const Text('Projected status: '),
                      Chip(label: Text(value)),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: gcashController,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'GCash Remitted'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: cashController,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Cash Remitted (optional if using denominations)'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: referenceController,
                  decoration: const InputDecoration(labelText: 'GCash Reference'),
                ),
                const SizedBox(height: 12),
                Text('Cash denominations', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: orderedDenominations.map((denomination) {
                    final key = denomination.key;
                    final label = _denominationLabel(key);
                    return SizedBox(
                      width: 110,
                      child: TextField(
                        controller: denominationControllers[key],
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(labelText: label),
                      ),
                    );
                  }).toList(),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: notesController,
                  maxLines: 2,
                  decoration: const InputDecoration(labelText: 'Entry Notes'),
                ),
                if (entryRows.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text(
                    'Remittance Piece History',
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 8),
                  ...entryRows.map((entry) => Card(
                        child: ListTile(
                          dense: true,
                          title: Text(
                            '#${entry['entry_sequence'] ?? 0} ${entry['entry_type'] ?? 'ENTRY'}',
                          ),
                          subtitle: Text(
                            'Cash ${_money(entry['cash_remitted'])} | GCash ${_money(entry['gcash_remitted'])}\n'
                            'Total ${_money(entry['total_remitted'])}\n'
                            '${(entry['notes'] ?? '').toString().trim().isEmpty ? '' : 'Note: ${entry['notes']}'}',
                          ),
                          isThreeLine: true,
                        ),
                      )),
                ],
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () => Navigator.of(context).pop(true),
                  child: const Text('Save Remittance'),
                ),
              ],
            ),
          ),
        ),
      );

      if (result != true) {
        return;
      }

      final body = <String, dynamic>{
        'cash_remitted': cashController.text.trim(),
        'gcash_remitted': gcashController.text.trim(),
        'gcash_reference': referenceController.text.trim(),
        'entry_notes': notesController.text.trim(),
      };
      for (final entry in denominationControllers.entries) {
        body[entry.key] = entry.value.text.trim().isEmpty ? '0' : entry.value.text.trim();
      }

      final saveResult = await widget.apiClient.saveRemittance(_token, deliveryRecordId, body: body);
      _showMessage((saveResult['message'] ?? 'Remittance saved.').toString());
      await _loadAll();
    } on ApiException catch (error) {
      _showMessage(error.message, isError: true);
    } catch (error) {
      _showMessage(
        'Unable to open remittance collector: ${error.toString()}',
        isError: true,
      );
    } finally {
      for (final controller in denominationControllers.values) {
        controller.dispose();
      }
      cashController?.dispose();
      gcashController?.dispose();
      referenceController?.dispose();
      notesController?.dispose();
      denominationCash?.dispose();
      thisEntryTotal?.dispose();
      projectedGrandTotal?.dispose();
      projectedStatus?.dispose();
    }
  }

  Future<void> _deletePendingRemittance(Map<String, dynamic> item) async {
    final deliveryRecordId = _safeInt(item['delivery_record_id']);
    if (deliveryRecordId == null) {
      _showMessage('Invalid delivery record ID.', isError: true);
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Pending Remittance'),
        content: const Text(
          'This will remove this pending remittance queue item. Continue?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton.tonal(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      final result = await widget.apiClient.deletePendingRemittance(_token, deliveryRecordId);
      _showMessage((result['message'] ?? 'Pending remittance deleted.').toString());
      await _loadAll();
    } on ApiException catch (error) {
      _showMessage(error.message, isError: true);
    }
  }

  Future<void> _generatePayroll() async {
    try {
      final riders = await widget.apiClient.fetchRiders(_token);
      if (!mounted) {
        return;
      }
      if (riders.isEmpty) {
        _showMessage('No active riders found.', isError: true);
        return;
      }

      var selectedRiderId = _safeInt(riders.first['id']) ?? 0;
      var selectedCutoff = 'FIRST';
      final monthController = TextEditingController(text: _currentMonth());

      final payload = await showDialog<Map<String, dynamic>>(
        context: context,
        builder: (context) => StatefulBuilder(
          builder: (context, setModalState) => AlertDialog(
            title: const Text('Generate Payroll'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<int>(
                    initialValue: selectedRiderId,
                    decoration: const InputDecoration(labelText: 'Rider'),
                    items: riders
                        .map(
                          (rider) => DropdownMenuItem<int>(
                            value: _safeInt(rider['id']) ?? 0,
                            child: Text(
                              '${rider['name'] ?? 'Unknown'} (${rider['rider_code'] ?? ''})',
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (value) {
                      if (value == null) {
                        return;
                      }
                      setModalState(() {
                        selectedRiderId = value;
                      });
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: monthController,
                    readOnly: true,
                    decoration: const InputDecoration(labelText: 'Payroll Month (YYYY-MM)'),
                    onTap: () async {
                      final initialMonth = _tryParseMonth(monthController.text) ?? DateTime.now();
                      final picked = await showDatePicker(
                        context: context,
                        initialDate: initialMonth,
                        firstDate: DateTime(2020, 1, 1),
                        lastDate: DateTime.now().add(const Duration(days: 365)),
                      );
                      if (picked != null) {
                        monthController.text = _formatMonth(picked);
                      }
                    },
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: selectedCutoff,
                    decoration: const InputDecoration(labelText: 'Cutoff'),
                    items: const [
                      DropdownMenuItem(value: 'FIRST', child: Text('First (1-15)')),
                      DropdownMenuItem(value: 'SECOND', child: Text('Second (16-end)')),
                    ],
                    onChanged: (value) {
                      if (value == null) {
                        return;
                      }
                      setModalState(() {
                        selectedCutoff = value;
                      });
                    },
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.of(context).pop({
                  'rider_id': selectedRiderId,
                  'payroll_month': monthController.text.trim(),
                  'cutoff_period': selectedCutoff,
                }),
                child: const Text('Generate'),
              ),
            ],
          ),
        ),
      );

      monthController.dispose();

      if (payload == null) {
        return;
      }

      final riderId = _safeInt(payload['rider_id']) ?? 0;
      final payrollMonth = (payload['payroll_month'] ?? '').toString().trim();
      final cutoffPeriod = (payload['cutoff_period'] ?? 'FIRST').toString();
      if (riderId <= 0 || !RegExp(r'^\d{4}-\d{2}$').hasMatch(payrollMonth)) {
        _showMessage('Provide valid payroll inputs.', isError: true);
        return;
      }

      final result = await widget.apiClient.generatePayroll(
        _token,
        riderId: riderId,
        payrollMonth: payrollMonth,
        cutoffPeriod: cutoffPeriod,
      );
      _showMessage((result['message'] ?? 'Payroll generated.').toString());
      await _loadAll();
    } on ApiException catch (error) {
      _showMessage(error.message, isError: true);
    }
  }

  Future<void> _releasePayroll(Map<String, dynamic> item) async {
    final methodController = ValueNotifier<String>('CASH');
    final referenceController = TextEditingController(
      text: (item['payout_reference'] ?? '').toString(),
    );

    final payload = await showDialog<Map<String, String>>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Release Payroll'),
        content: ValueListenableBuilder<String>(
          valueListenable: methodController,
          builder: (context, method, _) => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                key: ValueKey(method),
                initialValue: method,
                decoration: const InputDecoration(labelText: 'Payout Method'),
                items: const [
                  DropdownMenuItem(value: 'CASH', child: Text('Cash')),
                  DropdownMenuItem(value: 'BANK_TRANSFER', child: Text('Bank Transfer')),
                  DropdownMenuItem(value: 'E_WALLET', child: Text('E-Wallet')),
                  DropdownMenuItem(value: 'OTHER', child: Text('Other')),
                ],
                onChanged: (value) {
                  if (value != null) {
                    methodController.value = value;
                  }
                },
              ),
              const SizedBox(height: 12),
              TextField(
                controller: referenceController,
                decoration: const InputDecoration(
                  labelText: 'Reference',
                  helperText: 'Optional control number or note.',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop({
              'payout_method': methodController.value,
              'payout_reference': referenceController.text.trim(),
            }),
            child: const Text('Release'),
          ),
        ],
      ),
    );

    if (payload == null) {
      return;
    }

    try {
      await widget.apiClient.releasePayroll(
        _token,
        item['id'] as int,
        payoutMethod: payload['payout_method'] ?? 'CASH',
        payoutReference: payload['payout_reference'] ?? '',
      );
      _showMessage('Payroll released.');
      await _reloadPayrolls();
    } on ApiException catch (error) {
      _showMessage(error.message, isError: true);
    }
  }

  Future<void> _collectShortagePayment(Map<String, dynamic> item) async {
    final remittanceId = _safeInt(item['remittance_id']);
    if (remittanceId == null) {
      _showMessage('Invalid shortage record ID.', isError: true);
      return;
    }

    final outstanding = _safeDouble(item['outstanding_balance']);
    if (outstanding <= 0) {
      _showMessage('This shortage is already settled.');
      return;
    }

    final dateController = TextEditingController(text: _todayDate());
    final amountController = TextEditingController(text: outstanding.toStringAsFixed(2));
    final notesController = TextEditingController();

    final payload = await showDialog<Map<String, String>>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Collect Shortage Payment'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: dateController,
                readOnly: true,
                decoration: const InputDecoration(labelText: 'Payment Date'),
                onTap: () async {
                  final selected = await showDatePicker(
                    context: context,
                    initialDate: _tryParseDate(dateController.text) ?? DateTime.now(),
                    firstDate: DateTime(2020, 1, 1),
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                  );
                  if (selected != null) {
                    dateController.text = _formatDate(selected);
                  }
                },
              ),
              const SizedBox(height: 12),
              TextField(
                controller: amountController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  labelText: 'Amount',
                  helperText: 'Outstanding: ${_money(outstanding)}',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: notesController,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Notes',
                  helperText: 'Optional payment note.',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop({
              'payment_date': dateController.text.trim(),
              'amount': amountController.text.trim(),
              'notes': notesController.text.trim(),
            }),
            child: const Text('Collect'),
          ),
        ],
      ),
    );

    dateController.dispose();
    amountController.dispose();
    notesController.dispose();

    if (payload == null) {
      return;
    }

    try {
      final result = await widget.apiClient.collectShortage(
        _token,
        remittanceId,
        paymentDate: payload['payment_date'] ?? '',
        amount: payload['amount'] ?? '',
        notes: payload['notes'] ?? '',
      );
      final remaining = _safeDouble(result['remaining_balance']);
      _showMessage('Shortage payment recorded. Remaining: ${_money(remaining)}');
      await _loadAll();
    } on ApiException catch (error) {
      _showMessage(error.message, isError: true);
    }
  }

  Future<void> _logout() async {
    setState(() {
      _isLoggingOut = true;
    });
    await widget.sessionController.logout();
    if (mounted) {
      setState(() {
        _isLoggingOut = false;
      });
    }
  }

  Future<void> _downloadManualBackup() async {
    if (_isDownloadingBackup) {
      return;
    }

    setState(() {
      _isDownloadingBackup = true;
    });

    try {
      final file = await widget.apiClient.downloadManualBackup(_token);
      final message = await saveBackupFile(
        bytes: file.bytes,
        fileName: file.fileName,
      );
      _showMessage(message);
    } on ApiException catch (error) {
      _showMessage(error.message, isError: true);
    } catch (error) {
      _showMessage('Unable to save backup file: ${error.toString()}', isError: true);
    } finally {
      if (mounted) {
        setState(() {
          _isDownloadingBackup = false;
        });
      }
    }
  }

  void _showMessage(String message, {bool isError = false}) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Theme.of(context).colorScheme.error : null,
      ),
    );
  }

  String _money(dynamic value) {
    final number = switch (value) {
      int v => v.toDouble(),
      double v => v,
      String v => double.tryParse(v) ?? 0,
      _ => 0,
    };

    return 'PHP ${number.toStringAsFixed(2)}';
  }

  int? _safeInt(dynamic value) {
    if (value is int) {
      return value;
    }
    return int.tryParse(value.toString());
  }

  String _asText(dynamic value) => (value ?? '').toString().trim();

  double _safeDouble(dynamic value) {
    if (value is int) {
      return value.toDouble();
    }
    if (value is double) {
      return value;
    }
    return double.tryParse(value.toString()) ?? 0;
  }

  DateTime? _tryParseDate(String value) {
    try {
      return DateTime.parse(value);
    } catch (_) {
      return null;
    }
  }

  DateTime? _tryParseMonth(String value) {
    final text = value.trim();
    if (!RegExp(r'^\d{4}-\d{2}$').hasMatch(text)) {
      return null;
    }
    return _tryParseDate('$text-01');
  }

  String _formatDate(DateTime date) {
    final year = date.year.toString().padLeft(4, '0');
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '$year-$month-$day';
  }

  String _todayDate() => _formatDate(DateTime.now());

  String _formatMonth(DateTime date) {
    final year = date.year.toString().padLeft(4, '0');
    final month = date.month.toString().padLeft(2, '0');
    return '$year-$month';
  }

  String _currentMonth() => _formatMonth(DateTime.now());

  Map<String, double> _normalizeDenominations(dynamic source) {
    const fallback = <String, double>{
      'denom_025': 0.25,
      'denom_1': 1,
      'denom_5': 5,
      'denom_10': 10,
      'denom_20': 20,
      'denom_50': 50,
      'denom_100': 100,
      'denom_500': 500,
      'denom_1000': 1000,
    };

    if (source is! Map) {
      return fallback;
    }

    final normalized = <String, double>{};
    for (final entry in source.entries) {
      final key = entry.key.toString();
      final value = _safeDouble(entry.value);
      if (value > 0) {
        normalized[key] = value;
      }
    }

    if (normalized.isEmpty) {
      return fallback;
    }

    return normalized;
  }

  String _projectedVarianceType(double projectedTotal, double expectedTotal) {
    if (projectedTotal <= 0) {
      return 'PENDING';
    }
    final variance = projectedTotal - expectedTotal;
    if (variance > 0.005) {
      return 'OVER';
    }
    if (variance < -0.005) {
      return 'SHORT';
    }
    return 'BALANCED';
  }

  String _denominationLabel(String key) {
    switch (key) {
      case 'denom_025':
        return '25c';
      case 'denom_1':
        return 'PHP 1';
      case 'denom_5':
        return 'PHP 5';
      case 'denom_10':
        return 'PHP 10';
      case 'denom_20':
        return 'PHP 20';
      case 'denom_50':
        return 'PHP 50';
      case 'denom_100':
        return 'PHP 100';
      case 'denom_500':
        return 'PHP 500';
      case 'denom_1000':
        return 'PHP 1000';
      default:
        return key;
    }
  }
  String _metaText(Map<String, dynamic> meta) {
    final total = meta['total'] ?? meta['total_items'];
    if (total == null) {
      return 'Live data';
    }
    return '$total total';
  }

  Widget _summaryCard(String label, String value, IconData icon) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            CircleAvatar(
              backgroundColor: Theme.of(context).colorScheme.primaryContainer,
              child: Icon(icon),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: Theme.of(context).textTheme.labelLarge),
                  const SizedBox(height: 4),
                  Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSubmissionsTab() {
    if (_pendingSubmissions.isEmpty) {
      return const Center(child: Text('No pending submissions.'));
    }

    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _summaryCard('Pending submissions', _metaText(_pendingSubmissionMeta), Icons.assignment_late_outlined),
          const SizedBox(height: 12),
          ..._pendingSubmissions.map((item) {
            final rider = (item['rider'] as Map<String, dynamic>? ?? <String, dynamic>{});
            final account = (item['remittance_account'] as Map<String, dynamic>? ?? <String, dynamic>{});
            return Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${rider['name'] ?? 'Unknown Rider'} (${rider['rider_code'] ?? ''})', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    Text('Delivery date: ${item['delivery_date'] ?? ''}'),
                    Text('Successful deliveries: ${item['successful_deliveries'] ?? 0}'),
                    Text('Expected remittance: ${_money(item['expected_remittance'])}'),
                    Text('Remittance account: ${account['name'] ?? 'Not set'} ${_asText(account['number']).isNotEmpty ? '- ${account['number']}' : ''}'),
                    if ((item['notes'] ?? '').toString().trim().isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(item['notes'].toString()),
                    ],
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => _rejectSubmission(item),
                            child: const Text('Reject'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: FilledButton(
                            onPressed: () => _approveSubmission(item),
                            child: const Text('Approve'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildRemittancesTab() {
    if (_pendingRemittances.isEmpty) {
      return const Center(child: Text('No pending remittances.'));
    }

    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _summaryCard('Pending remittances', _metaText(_pendingRemittanceMeta), Icons.payments_outlined),
          const SizedBox(height: 12),
          ..._pendingRemittances.map((item) {
            final rider = (item['rider'] as Map<String, dynamic>? ?? <String, dynamic>{});
            final account = (item['remittance_account'] as Map<String, dynamic>? ?? <String, dynamic>{});
            return Card(
              child: ListTile(
                contentPadding: const EdgeInsets.all(16),
                title: Text('${rider['name'] ?? ''} (${rider['rider_code'] ?? ''})'),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 8),
                    Text('Delivery date: ${item['delivery_date'] ?? ''}'),
                    Text('Expected remittance: ${_money(item['expected_remittance'])}'),
                    Text('Aging: ${item['aging_days'] ?? 0} day(s)'),
                    Text('Status: ${item['pending_status'] ?? ''}'),
                    Text('Account: ${account['name'] ?? 'Not set'} ${_asText(account['number']).isNotEmpty ? '- ${account['number']}' : ''}'),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: FilledButton.tonal(
                            onPressed: () {
                              final deliveryRecordId = _safeInt(item['delivery_record_id']);
                              if (deliveryRecordId == null) {
                                _showMessage('Invalid delivery record ID.', isError: true);
                                return;
                              }
                              _openRemittanceCollector(deliveryRecordId);
                            },
                            child: const Text('Review & Collect'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => _deletePendingRemittance(item),
                            child: const Text('Delete'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildShortagesTab() {
    if (_shortages.isEmpty) {
      return const Center(child: Text('No shortages found.'));
    }

    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _summaryCard('Shortage ledger', _metaText(_shortageMeta), Icons.warning_amber_rounded),
          const SizedBox(height: 12),
          ..._shortages.map((item) {
            final rider = (item['rider'] as Map<String, dynamic>? ?? <String, dynamic>{});
            final status = (item['shortage_status'] ?? 'OPEN').toString();
            return Card(
              child: ListTile(
                contentPadding: const EdgeInsets.all(16),
                title: Text('${rider['name'] ?? ''} (${rider['rider_code'] ?? ''})'),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 8),
                    Text('Delivery date: ${item['delivery_date'] ?? ''}'),
                    Text('Shortage: ${_money(item['variance_amount'])}'),
                    Text('Paid: ${_money(item['paid_amount'])}'),
                    Text('Outstanding: ${_money(item['outstanding_balance'])}'),
                    if (status != 'SETTLED') ...[
                      const SizedBox(height: 12),
                      FilledButton.tonal(
                        onPressed: () => _collectShortagePayment(item),
                        child: const Text('Collect Payment'),
                      ),
                    ],
                  ],
                ),
                trailing: Chip(
                  label: Text(status),
                  backgroundColor: status == 'SETTLED'
                      ? Colors.green.shade100
                      : Colors.orange.shade100,
                ),
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildPayrollsTab() {
    return RefreshIndicator(
      onRefresh: _reloadPayrolls,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _summaryCard('Payroll batches', _metaText(_payrollMeta), Icons.account_balance_wallet_outlined),
          const SizedBox(height: 12),
          FilledButton.tonalIcon(
            onPressed: _generatePayroll,
            icon: const Icon(Icons.playlist_add_check_circle_outlined),
            label: const Text('Generate Payroll'),
          ),
          const SizedBox(height: 12),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: '', label: Text('All')),
              ButtonSegment(value: 'GENERATED', label: Text('Generated')),
              ButtonSegment(value: 'RELEASED', label: Text('Released')),
              ButtonSegment(value: 'RECEIVED', label: Text('Received')),
            ],
            selected: {_payrollStatusFilter},
            onSelectionChanged: (selection) {
              setState(() {
                _payrollStatusFilter = selection.first;
              });
              _reloadPayrolls();
            },
          ),
          const SizedBox(height: 12),
          if (_payrolls.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Text('No payroll records found for the selected filter.'),
              ),
            )
          else
            ..._payrolls.map((item) {
              final rider = (item['rider'] as Map<String, dynamic>? ?? <String, dynamic>{});
              final status = (item['payroll_status'] ?? 'GENERATED').toString();
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${rider['name'] ?? ''} (${rider['rider_code'] ?? ''})', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                      const SizedBox(height: 8),
                      Text('Coverage: ${item['start_date'] ?? ''} to ${item['end_date'] ?? ''}'),
                      Text('Gross: ${_money(item['gross_earnings'])}'),
                      Text('Net pay: ${_money(item['net_pay'])}'),
                      Text('Status: $status'),
                      if ((item['payout_method'] ?? '').toString().isNotEmpty)
                        Text('Payout: ${item['payout_method']} ${_asText(item['payout_reference']).isNotEmpty ? '- ${item['payout_reference']}' : ''}'),
                      if ((item['released_at'] ?? '').toString().isNotEmpty)
                        Text('Released: ${item['released_at']}'),
                      if ((item['received_at'] ?? '').toString().isNotEmpty)
                        Text('Received: ${item['received_at']}'),
                      if (status == 'GENERATED') ...[
                        const SizedBox(height: 12),
                        FilledButton(
                          onPressed: () => _releasePayroll(item),
                          child: const Text('Release Payroll'),
                        ),
                      ],
                    ],
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }

  Widget _buildSettingsTab() {
    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Manual Backup',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Generate a full SQL backup from the server. '
                    'Use this before end-of-day sign-off when you are not using automated VPS backups.',
                  ),
                  const SizedBox(height: 14),
                  FilledButton.icon(
                    onPressed: _isDownloadingBackup ? null : _downloadManualBackup,
                    icon: _isDownloadingBackup
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.download_rounded),
                    label: Text(_isDownloadingBackup ? 'Preparing backup...' : 'Download SQL Backup'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final session = widget.sessionController.session;

    return DefaultTabController(
      length: 5,
      child: Scaffold(
        appBar: AppBar(
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('J&T Admin Portal'),
              if (session != null)
                Text(
                  session.username,
                  style: Theme.of(context).textTheme.labelMedium,
                ),
            ],
          ),
          actions: [
            IconButton(
              onPressed: _isLoading ? null : _loadAll,
              icon: const Icon(Icons.refresh),
            ),
            IconButton(
              onPressed: _isLoggingOut ? null : _logout,
              icon: _isLoggingOut
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.logout),
            ),
          ],
          bottom: const TabBar(
            isScrollable: true,
            tabs: [
              Tab(text: 'Submissions'),
              Tab(text: 'Remittances'),
              Tab(text: 'Shortages'),
              Tab(text: 'Payrolls'),
              Tab(text: 'Settings'),
            ],
          ),
        ),
        body: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(_error!, textAlign: TextAlign.center),
                          const SizedBox(height: 16),
                          FilledButton(
                            onPressed: _loadAll,
                            child: const Text('Retry'),
                          ),
                        ],
                      ),
                    ),
                  )
                : TabBarView(
                    children: [
                      _buildSubmissionsTab(),
                      _buildRemittancesTab(),
                      _buildShortagesTab(),
                      _buildPayrollsTab(),
                      _buildSettingsTab(),
                    ],
                  ),
      ),
    );
  }
}







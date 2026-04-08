import 'package:flutter/material.dart';

import '../controllers/session_controller.dart';
import '../services/api_client.dart';

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
    final controller = TextEditingController(text: '10');
    final confirmed = await showDialog<double>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Approve Submission'),
        content: TextField(
          controller: controller,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(
            labelText: 'Commission Rate',
            helperText: 'Amount earned per successful delivery.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(double.tryParse(controller.text.trim())),
            child: const Text('Approve'),
          ),
        ],
      ),
    );

    if (confirmed == null || confirmed <= 0) {
      return;
    }

    try {
      await widget.apiClient.approveSubmission(_token, item['id'] as int, confirmed);
      _showMessage('Submission approved.');
      await _loadAll();
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
                    Text('Remittance account: ${account['name'] ?? 'Not set'} ${((account['number'] ?? '') as String).isNotEmpty ? '- ${account['number']}' : ''}'),
                    if ((item['notes'] ?? '').toString().trim().isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(item['notes'].toString()),
                    ],
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: FilledButton(
                            onPressed: () => _approveSubmission(item),
                            child: const Text('Approve'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: FilledButton.tonal(
                            onPressed: () => _rejectSubmission(item),
                            child: const Text('Reject'),
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
                    Text('Account: ${account['name'] ?? 'Not set'} ${((account['number'] ?? '') as String).isNotEmpty ? '- ${account['number']}' : ''}'),
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
                        Text('Payout: ${item['payout_method']} ${((item['payout_reference'] ?? '') as String).isNotEmpty ? '- ${item['payout_reference']}' : ''}'),
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

  @override
  Widget build(BuildContext context) {
    final session = widget.sessionController.session;

    return DefaultTabController(
      length: 4,
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
                    ],
                  ),
      ),
    );
  }
}




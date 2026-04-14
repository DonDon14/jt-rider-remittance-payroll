import 'package:flutter/material.dart';

import '../controllers/session_controller.dart';
import '../services/api_client.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    super.key,
    required this.sessionController,
  });

  final SessionController sessionController;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();

  bool _isSubmitting = false;
  bool _obscurePassword = true;
  String? _error;

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _isSubmitting = true;
      _error = null;
    });

    try {
      await widget.sessionController.login(
        username: _usernameController.text.trim(),
        password: _passwordController.text,
      );
    } on ApiException catch (error) {
      setState(() {
        _error = error.message;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.message)),
        );
      }
    } catch (error) {
      final message = 'Unable to sign in right now. ${error.toString()}';
      setState(() {
        _error = message;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(message)),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  Future<void> _openForgotPasswordSheet() async {
    final usernameController = TextEditingController(text: _usernameController.text.trim());
    final recoveryKeyController = TextEditingController();
    bool obscureRecoveryKey = true;
    bool submitting = false;
    String? localError;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setLocalState) {
            Future<void> submitForgotPassword() async {
              if (submitting) {
                return;
              }

              setLocalState(() {
                submitting = true;
                localError = null;
              });

              final navigator = Navigator.of(this.context);
              try {
                final temporaryPassword = await widget.sessionController.forgotPassword(
                  username: usernameController.text.trim(),
                  recoveryKey: recoveryKeyController.text.trim(),
                );

                if (!mounted) {
                  return;
                }

                navigator.pop();
                ScaffoldMessenger.of(this.context).showSnackBar(
                  SnackBar(
                    content: Text(
                      temporaryPassword.isEmpty
                          ? 'Temporary password issued. Change it immediately after login.'
                          : 'Temporary password: $temporaryPassword',
                    ),
                  ),
                );
              } on ApiException catch (error) {
                setLocalState(() {
                  localError = error.message;
                });
              } finally {
                if (context.mounted) {
                  setLocalState(() {
                    submitting = false;
                  });
                }
              }
            }

            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 8,
                bottom: MediaQuery.of(context).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Forgot Password', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 10),
                  const Text('For admin accounts, provide your username and admin recovery key.'),
                  const SizedBox(height: 14),
                  TextField(
                    controller: usernameController,
                    decoration: const InputDecoration(labelText: 'Username'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: recoveryKeyController,
                    obscureText: obscureRecoveryKey,
                    decoration: InputDecoration(
                      labelText: 'Admin Recovery Key',
                      suffixIcon: IconButton(
                        onPressed: () => setLocalState(() => obscureRecoveryKey = !obscureRecoveryKey),
                        icon: Icon(obscureRecoveryKey ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                      ),
                    ),
                  ),
                  if (localError != null) ...[
                    const SizedBox(height: 10),
                    Text(localError!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  ],
                  const SizedBox(height: 14),
                  FilledButton(
                    onPressed: submitting ? null : submitForgotPassword,
                    child: Text(submitting ? 'Processing...' : 'Issue Temporary Password'),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFFF4E6DB), Color(0xFFF6F1EB), Color(0xFFE8EEF2)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 430),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(28),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(32),
                        gradient: const LinearGradient(
                          colors: [Color(0xFF14334F), Color(0xFF285178), Color(0xFF8C451E)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x2B14334F),
                            blurRadius: 28,
                            offset: Offset(0, 18),
                          ),
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 58,
                            height: 58,
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.14),
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: const Icon(Icons.admin_panel_settings_outlined, color: Colors.white, size: 28),
                          ),
                          const SizedBox(height: 20),
                          Text(
                            'JT Admin',
                            style: theme.textTheme.headlineMedium?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Approve requests, release payroll, and monitor remittance queues from the field.',
                            style: theme.textTheme.bodyLarge?.copyWith(
                              color: Colors.white.withValues(alpha: 0.92),
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 22),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(
                                'Admin sign in',
                                style: theme.textTheme.headlineSmall?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  color: const Color(0xFF18212B),
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                'Use your admin credentials for the live operations portal.',
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  color: const Color(0xFF5E6875),
                                ),
                              ),
                              const SizedBox(height: 20),
                              TextFormField(
                                controller: _usernameController,
                                decoration: const InputDecoration(
                                  labelText: 'Username',
                                  prefixIcon: Icon(Icons.person_outline_rounded),
                                ),
                                validator: (value) => value == null || value.trim().isEmpty ? 'Username is required.' : null,
                              ),
                              const SizedBox(height: 16),
                              TextFormField(
                                controller: _passwordController,
                                decoration: InputDecoration(
                                  labelText: 'Password',
                                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                                  suffixIcon: IconButton(
                                    onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                                    icon: Icon(_obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                                  ),
                                ),
                                obscureText: _obscurePassword,
                                validator: (value) => value == null || value.isEmpty ? 'Password is required.' : null,
                                onFieldSubmitted: (_) => _isSubmitting ? null : _submit(),
                              ),
                              Align(
                                alignment: Alignment.centerRight,
                                child: TextButton(
                                  onPressed: _isSubmitting ? null : _openForgotPasswordSheet,
                                  child: const Text('Forgot password?'),
                                ),
                              ),
                              if (_error != null) ...[
                                const SizedBox(height: 14),
                                Container(
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: theme.colorScheme.errorContainer,
                                    borderRadius: BorderRadius.circular(18),
                                  ),
                                  child: Text(
                                    _error!,
                                    style: TextStyle(color: theme.colorScheme.onErrorContainer),
                                  ),
                                ),
                              ],
                              const SizedBox(height: 22),
                              FilledButton.icon(
                                onPressed: _isSubmitting ? null : _submit,
                                icon: _isSubmitting
                                    ? const SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                      )
                                    : const Icon(Icons.arrow_forward_rounded),
                                label: Text(_isSubmitting ? 'Signing in...' : 'Open Admin Portal'),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

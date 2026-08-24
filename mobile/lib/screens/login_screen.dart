import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../core/api_client.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_widgets.dart';
import 'home_shell.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> with SingleTickerProviderStateMixin {
  final email = TextEditingController();
  final password = TextEditingController();
  bool loading = false;
  bool obscure = true;
  bool remember = false;
  String? error;
  late final AnimationController _anim;
  late final Animation<double> _fade;
  late final Animation<Offset> _slide;

  static const _rememberKey = 'seas_remember_me';
  static const _emailKey = 'seas_remember_email';
  static const _passwordKey = 'seas_remember_password';

  @override
  void initState() {
    super.initState();
    _anim = AnimationController(vsync: this, duration: const Duration(milliseconds: 650));
    _fade = CurvedAnimation(parent: _anim, curve: Curves.easeOutCubic);
    _slide = Tween<Offset>(begin: const Offset(0, 0.06), end: Offset.zero).animate(
      CurvedAnimation(parent: _anim, curve: Curves.easeOutCubic),
    );
    _anim.forward();
    _loadRemembered();
  }

  Future<void> _loadRemembered() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getBool(_rememberKey) ?? false;
    if (!saved) return;
    if (!mounted) return;
    setState(() {
      remember = true;
      email.text = prefs.getString(_emailKey) ?? '';
      password.text = prefs.getString(_passwordKey) ?? '';
    });
  }

  Future<void> _persistRemember() async {
    final prefs = await SharedPreferences.getInstance();
    if (remember) {
      await prefs.setBool(_rememberKey, true);
      await prefs.setString(_emailKey, email.text.trim());
      await prefs.setString(_passwordKey, password.text);
    } else {
      await prefs.setBool(_rememberKey, false);
      await prefs.remove(_emailKey);
      await prefs.remove(_passwordKey);
    }
  }

  @override
  void dispose() {
    _anim.dispose();
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      await _persistRemember();
      await api.login(email.text.trim(), password.text);
      if (!mounted) return;
      Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const HomeShell()),
        (_) => false,
      );
    } catch (e) {
      setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final wide = MediaQuery.sizeOf(context).width >= 900;

    return Scaffold(
      backgroundColor: SeasColors.white,
      body: wide ? _DesktopLogin(form: _formPanel()) : _MobileLogin(form: _formPanel()),
    );
  }

  Widget _formPanel() {
    return FadeTransition(
      opacity: _fade,
      child: SlideTransition(
        position: _slide,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Sign in', style: GoogleFonts.plusJakartaSans(fontSize: 28, fontWeight: FontWeight.w800, letterSpacing: -0.6)),
            const SizedBox(height: 6),
            Text(
              'Field Executive · Manager · Project Manager · Super Admin',
              style: TextStyle(color: SeasColors.ink400, fontSize: 14),
            ),
            const SizedBox(height: 8),
            Text(
              'Release APK → mrhari.co.in. Create app users on production admin, not local.',
              style: GoogleFonts.plusJakartaSans(fontSize: 12, color: SeasColors.ink400, height: 1.35),
            ),
            const SizedBox(height: 22),
            SeasCard(
              padding: const EdgeInsets.fromLTRB(20, 22, 20, 22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Login ID / Email', style: GoogleFonts.plusJakartaSans(fontSize: 13, fontWeight: FontWeight.w700, color: SeasColors.ink400)),
                  const SizedBox(height: 8),
                  TextField(
                    controller: email,
                    keyboardType: TextInputType.emailAddress,
                    autofillHints: const [AutofillHints.username],
                    decoration: const InputDecoration(hintText: 'Enter login ID / email'),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: Text('Password', style: GoogleFonts.plusJakartaSans(fontSize: 13, fontWeight: FontWeight.w700, color: SeasColors.ink400)),
                      ),
                      Text('Forgot?', style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w700, color: SeasColors.volt)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: password,
                    obscureText: obscure,
                    autofillHints: const [AutofillHints.password],
                    onSubmitted: (_) => _login(),
                    decoration: InputDecoration(
                      hintText: 'Enter password',
                      suffixIcon: IconButton(
                        onPressed: () => setState(() => obscure = !obscure),
                        icon: Icon(obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined, color: SeasColors.ink400),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  InkWell(
                    onTap: () => setState(() => remember = !remember),
                    borderRadius: BorderRadius.circular(8),
                    child: Row(
                      children: [
                        SizedBox(
                          height: 22,
                          width: 22,
                          child: Checkbox(
                            value: remember,
                            activeColor: SeasColors.volt,
                            onChanged: (v) => setState(() => remember = v ?? false),
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Text('Remember me', style: TextStyle(color: SeasColors.ink400, fontSize: 14)),
                      ],
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: SeasColors.voltSoft,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0x33E10600)),
                      ),
                      child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600, fontSize: 13)),
                    ),
                  ],
                  const SizedBox(height: 18),
                  SeasPrimaryButton(label: loading ? 'SIGNING IN…' : 'LOGIN', onPressed: _login, loading: loading),
                ],
              ),
            ),
            const SizedBox(height: 20),
            Text(
              'Admin users → Web portal only',
              textAlign: TextAlign.center,
              style: GoogleFonts.plusJakartaSans(fontSize: 12, color: SeasColors.ink400),
            ),
          ],
        ),
      ),
    );
  }
}

class _MobileLogin extends StatelessWidget {
  const _MobileLogin({required this.form});
  final Widget form;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned.fill(
          child: DecoratedBox(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [SeasColors.white, SeasColors.canvasSoft, SeasColors.canvas],
              ),
            ),
          ),
        ),
        SafeArea(
          child: CustomScrollView(
            physics: const BouncingScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                  child: _BrandHero(compact: true),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 28, 20, 40),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 440),
                    child: form,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _DesktopLogin extends StatelessWidget {
  const _DesktopLogin({required this.form});
  final Widget form;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          flex: 5,
          child: _BrandHero(compact: false),
        ),
        Expanded(
          flex: 5,
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 48, vertical: 40),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: form,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _BrandHero extends StatelessWidget {
  const _BrandHero({required this.compact});
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: compact ? 220 : double.infinity,
      width: double.infinity,
      decoration: BoxDecoration(
        borderRadius: compact ? BorderRadius.circular(28) : BorderRadius.zero,
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [SeasColors.ink950, SeasColors.ink800, Color(0xFF2A0A0A)],
        ),
        boxShadow: compact ? SeasShadows.seasLg : null,
      ),
      child: Stack(
        children: [
          Positioned(
            right: -40,
            top: 24,
            child: Container(
              height: 180,
              width: 180,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: SeasColors.volt.withValues(alpha: 0.35),
                boxShadow: [BoxShadow(color: SeasColors.volt.withValues(alpha: 0.45), blurRadius: 80)],
              ),
            ),
          ),
          Positioned.fill(
            child: CustomPaint(painter: _GridPainter(color: Colors.white.withValues(alpha: 0.07))),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(compact ? 22 : 40, compact ? 22 : 40, compact ? 22 : 40, compact ? 22 : 40),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const SeasLogoMark(size: 48),
                    const SizedBox(width: 12),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('SEAS', style: GoogleFonts.plusJakartaSans(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
                        Text(
                          'SMART ENERGY AUDIT',
                          style: GoogleFonts.plusJakartaSans(color: Colors.white.withValues(alpha: 0.45), fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 2.4),
                        ),
                      ],
                    ),
                  ],
                ),
                const Spacer(),
                Text(
                  'Audit the grid.',
                  style: GoogleFonts.plusJakartaSans(
                    color: Colors.white,
                    fontSize: compact ? 28 : 42,
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                    letterSpacing: -0.8,
                  ),
                ),
                Text(
                  'One DTR at a time.',
                  style: GoogleFonts.plusJakartaSans(
                    color: SeasColors.volt,
                    fontSize: compact ? 28 : 42,
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                    letterSpacing: -0.8,
                  ),
                ),
                if (!compact) ...[
                  const SizedBox(height: 16),
                  Text(
                    'From feeder to consumer — capture, approve, and close field surveys.',
                    style: TextStyle(color: Colors.white.withValues(alpha: 0.55), fontSize: 15, height: 1.45),
                  ),
                  const Spacer(),
                  Text('MPMKVVCL · Field Operations', style: TextStyle(color: Colors.white.withValues(alpha: 0.35), fontSize: 12)),
                ] else ...[
                  const SizedBox(height: 10),
                  Text(
                    'Field surveys on mobile. Admin stays on web.',
                    style: TextStyle(color: Colors.white.withValues(alpha: 0.55), fontSize: 13),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GridPainter extends CustomPainter {
  _GridPainter({required this.color});
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = 1;
    const step = 36.0;
    for (double x = 0; x < size.width; x += step) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), paint);
    }
    for (double y = 0; y < size.height; y += step) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), paint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

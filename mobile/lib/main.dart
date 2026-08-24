import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'core/api_client.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'theme/seas_colors.dart';
import 'theme/seas_theme.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.dark,
    systemNavigationBarColor: SeasColors.white,
    systemNavigationBarIconBrightness: Brightness.dark,
  ));
  await api.loadToken();
  runApp(const SeasApp());
}

class SeasApp extends StatelessWidget {
  const SeasApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SEAS Field',
      debugShowCheckedModeBanner: false,
      theme: buildSeasTheme(),
      builder: (context, child) {
        // Desktop / laptop web: center a phone-frame so UI doesn't stretch awkwardly
        if (!kIsWeb) return child ?? const SizedBox.shrink();
        return LayoutBuilder(builder: (context, constraints) {
          final wide = constraints.maxWidth >= 900;
          if (!wide) return child ?? const SizedBox.shrink();
          final frameW = constraints.maxWidth >= 1400 ? 480.0 : 430.0;
          final frameH = (constraints.maxHeight * 0.92).clamp(640.0, 920.0);
          return ColoredBox(
            color: const Color(0xFF0A0A0A),
            child: Center(
              child: Container(
                width: frameW,
                height: frameH,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(28),
                  border: Border.all(color: const Color(0x22FFFFFF)),
                  boxShadow: const [
                    BoxShadow(color: Color(0x66E10600), blurRadius: 48, offset: Offset(0, 16)),
                    BoxShadow(color: Color(0x88000000), blurRadius: 40, offset: Offset(0, 20)),
                  ],
                ),
                clipBehavior: Clip.antiAlias,
                child: MediaQuery(
                  data: MediaQuery.of(context).copyWith(
                    size: Size(frameW, frameH),
                    padding: EdgeInsets.zero,
                    viewPadding: EdgeInsets.zero,
                    viewInsets: EdgeInsets.zero,
                  ),
                  child: child ?? const SizedBox.shrink(),
                ),
              ),
            ),
          );
        });
      },
      home: api.isLoggedIn ? const HomeShell() : const LoginScreen(),
    );
  }
}

Future<Map<String, dynamic>?> loadSavedUser() async {
  final prefs = await SharedPreferences.getInstance();
  final raw = prefs.getString('seas_user');
  if (raw == null) return null;
  return jsonDecode(raw) as Map<String, dynamic>;
}

Future<void> saveSavedUser(Map<String, dynamic> user) async {
  await api.saveUser(user);
}

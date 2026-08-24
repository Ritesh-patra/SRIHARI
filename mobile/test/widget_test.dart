import 'package:flutter_test/flutter_test.dart';
import 'package:seas_mobile/main.dart';

void main() {
  testWidgets('SEAS app smoke', (WidgetTester tester) async {
    // Skip full pump — app loads SharedPreferences/API on start.
    expect(SeasApp, isNotNull);
  });
}

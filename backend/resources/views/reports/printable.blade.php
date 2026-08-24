<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Survey {{ $survey->dtr_code }}</title>
    <style>
        body{font-family:Arial,sans-serif;padding:24px;color:#111}
        h1{margin:0 0 8px}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        td,th{border:1px solid #ddd;padding:8px;text-align:left;font-size:13px}
        .muted{color:#666;font-size:12px}
        @media print{.no-print{display:none}}
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>
    <h1>SEAS · DTR Survey</h1>
    <p class="muted">{{ $survey->dtr_name }} ({{ $survey->dtr_code }}) · {{ $survey->status }}</p>
    <table>
        <tr><th>Surveyor</th><td>{{ $survey->surveyor?->name }}</td><th>Date</th><td>{{ $survey->surveyed_at }}</td></tr>
        <tr><th>Region</th><td>{{ $survey->region?->name }}</td><th>Circle</th><td>{{ $survey->circle?->name }}</td></tr>
        <tr><th>Division</th><td>{{ $survey->division?->name }}</td><th>Zone</th><td>{{ $survey->zone?->name }}</td></tr>
        <tr><th>Feeder</th><td colspan="3">{{ $survey->feeder_code }} · {{ $survey->feeder_name }}</td></tr>
        <tr><th>Capacity</th><td>{{ $survey->dtr_capacity_kva }} kVA</td><th>Condition</th><td>{{ $survey->dtr_condition }}</td></tr>
        <tr><th>Smart Meter</th><td>{{ $survey->smart_meter_status }}</td><th>New MSN</th><td>{{ $survey->new_msn }}</td></tr>
        <tr><th>GPS</th><td colspan="3">{{ $survey->latitude }}, {{ $survey->longitude }} (±{{ $survey->gps_accuracy }}m)</td></tr>
        <tr><th>Observation</th><td colspan="3">{{ $survey->observation }}</td></tr>
    </table>
</body>
</html>

<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $heading }}</title></head>
<body style="margin:0;background:#f7f5f0;font-family:Arial,sans-serif;color:#182231">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:28px 12px">
<table role="presentation" width="600" style="width:100%;max-width:600px;background:#fff;border:1px solid #e7e1d6;border-radius:16px" cellspacing="0" cellpadding="0">
<tr><td style="padding:24px;border-bottom:3px solid #f5ad16"><img src="{{ $message->embed(public_path('images/imprint-customs-mark.png')) }}" width="52" height="52" alt="Imprint Customs" style="vertical-align:middle;margin-right:12px"><strong>IMPRINT CUSTOMS</strong></td></tr>
<tr><td style="padding:28px"><h1 style="font-size:23px;margin:0 0 20px">{{ $heading }}</h1><div style="font-size:15px;line-height:1.7;white-space:pre-line">{{ $body }}</div>
@if($trackingUrl)<p style="margin:24px 0"><a href="{{ $trackingUrl }}" style="background:#f5ad16;color:#182231;padding:13px 20px;border-radius:8px;text-decoration:none;display:inline-block;font-weight:bold">Track your inquiry</a></p><p style="font-size:12px;color:#64748b">Use your reference number and email. If this temporary link expires, ask our team for the current form link.</p>@endif
</td></tr><tr><td style="padding:20px 28px;background:#faf8f4;font-size:12px;color:#64748b">Imprint Customs · Sent by Imprint Hub</td></tr>
</table></td></tr></table></body></html>

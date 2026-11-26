<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unit Transfer Confirmed</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 0; color: #3d4149; }
        .wrapper { max-width: 620px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1e1e2d; padding: 30px 40px; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .header p  { color: #9d9fb0; margin: 6px 0 0; font-size: 13px; }
        .body { padding: 36px 40px; }
        .greeting { font-size: 15px; margin-bottom: 24px; }
        .summary-box { background: #f5f8ff; border: 1px solid #d5e3ff; border-radius: 6px; padding: 20px 24px; margin-bottom: 24px; }
        .summary-box h3 { margin: 0 0 14px; font-size: 14px; text-transform: uppercase; color: #3699ff; letter-spacing: .5px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .row .label { color: #7a849e; }
        .row .value { font-weight: 600; }
        .divider { border: none; border-top: 1px solid #ebebeb; margin: 24px 0; }
        .finance-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px; }
        .finance-row.total { font-weight: 700; font-size: 15px; border-top: 1px solid #ddd; padding-top: 10px; margin-top: 6px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .footer { background: #f5f5f5; padding: 20px 40px; text-align: center; font-size: 12px; color: #9d9fb0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Unit Transfer Confirmed</h1>
        <p>Your unit transfer has been approved and executed</p>
    </div>

    <div class="body">
        <p class="greeting">Dear {{ $tenantName }},</p>
        <p style="font-size:14px; margin-bottom:24px;">
            Your unit transfer has been successfully processed. Below is a full summary of the move.
        </p>

        {{-- Transfer Summary --}}
        <div class="summary-box">
            <h3>Transfer Details</h3>
            <div class="row"><span class="label">Transfer Reference</span><span class="value">{{ $movement->ref }}</span></div>
            <div class="row"><span class="label">Move Type</span><span class="value">{{ $movement->move_type === 'end_of_month' ? 'End of Month' : 'Mid Month' }}</span></div>
            <div class="row"><span class="label">Transfer Date</span><span class="value">{{ \Carbon\Carbon::parse($movement->transfer_date)->format('d M Y') }}</span></div>
            <div class="row"><span class="label">Status</span><span class="value"><span class="badge badge-success">Completed</span></span></div>
        </div>

        {{-- Unit Change --}}
        <div class="summary-box">
            <h3>Unit Change</h3>
            <div class="row"><span class="label">Previous Unit</span><span class="value">{{ $movement->from_unit_label }}</span></div>
            <div class="row"><span class="label">New Unit</span><span class="value">{{ $movement->to_unit_label }}</span></div>
            <div class="row"><span class="label">Complex</span><span class="value">{{ optional($movement->oldTenancy)->complex_name }}</span></div>
        </div>

        {{-- Financial --}}
        <div class="summary-box">
            <h3>Financial Summary</h3>
            <div class="finance-row"><span>Previous Rent</span><span>{{ $currency }} {{ number_format($movement->old_rent, 2) }}</span></div>
            <div class="finance-row"><span>New Rent</span><span>{{ $currency }} {{ number_format($movement->new_rent, 2) }}</span></div>
            <hr class="divider" style="margin:10px 0;">

            @if ($movement->deposit_adjustment_type !== 'none' && $movement->deposit_adjustment > 0)
            <div class="finance-row"><span>Deposit Adjustment ({{ $movement->deposit_adjustment_type === 'top_up' ? 'Top-up' : 'Refund' }})</span>
                <span @if($movement->deposit_adjustment_type === 'top_up') style="color:#dc3545" @else style="color:#28a745" @endif>
                    {{ $movement->deposit_adjustment_type === 'refund' ? '-' : '+' }}{{ $currency }} {{ number_format($movement->deposit_adjustment, 2) }}
                </span>
            </div>
            @endif

            @if ($movement->proration_amount != 0)
            <div class="finance-row">
                <span>Proration Adjustment</span>
                <span @if($movement->proration_amount > 0) style="color:#dc3545" @else style="color:#28a745" @endif>
                    {{ $movement->proration_amount > 0 ? '+' : '' }}{{ $currency }} {{ number_format($movement->proration_amount, 2) }}
                </span>
            </div>
            @endif
        </div>

        @if ($movement->notes)
        <div class="summary-box">
            <h3>Notes</h3>
            <p style="font-size:14px;margin:0;">{{ $movement->notes }}</p>
        </div>
        @endif

        <p style="font-size:14px;color:#6c757d;margin-top:24px;">
            If you have any questions about this transfer, please contact your property manager.
        </p>
    </div>

    <div class="footer">
        <p style="margin:0;">This is an automated notification. Please do not reply to this email.</p>
    </div>
</div>
</body>
</html>

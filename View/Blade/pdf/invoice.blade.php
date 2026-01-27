<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #1f2937;
        }

        .container {
            padding: 40px;
        }

        /* Header */
        .header {
            margin-bottom: 40px;
        }

        .header-content {
            display: table;
            width: 100%;
        }

        .logo-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .logo-section h1 {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 4px;
        }

        .logo-section p {
            color: #6b7280;
            font-size: 11px;
        }

        .invoice-info {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: top;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 8px;
        }

        .invoice-number {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .invoice-date {
            font-size: 11px;
            color: #9ca3af;
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 8px;
        }

        .status-paid {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-overdue {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Addresses */
        .addresses {
            display: table;
            width: 100%;
            margin-bottom: 40px;
        }

        .address-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .address-box h3 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .address-box p {
            color: #374151;
            font-size: 11px;
            line-height: 1.6;
        }

        .address-box strong {
            color: #111827;
            font-weight: 600;
        }

        /* Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table thead th {
            background-color: #f9fafb;
            padding: 12px 16px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            border-bottom: 2px solid #e5e7eb;
        }

        .items-table thead th:last-child {
            text-align: right;
        }

        .items-table tbody td {
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        .items-table tbody td:last-child {
            text-align: right;
        }

        .item-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 2px;
        }

        .item-description {
            font-size: 10px;
            color: #6b7280;
        }

        .item-quantity {
            color: #6b7280;
        }

        .item-price {
            font-weight: 500;
            color: #111827;
        }

        /* Totals */
        .totals {
            width: 100%;
        }

        .totals-row {
            display: table;
            width: 100%;
        }

        .totals-spacer {
            display: table-cell;
            width: 60%;
        }

        .totals-content {
            display: table-cell;
            width: 40%;
        }

        .total-line {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .total-label {
            display: table-cell;
            text-align: left;
            color: #6b7280;
            font-size: 11px;
        }

        .total-value {
            display: table-cell;
            text-align: right;
            font-weight: 500;
            color: #374151;
        }

        .total-line.grand-total {
            border-top: 2px solid #e5e7eb;
            padding-top: 12px;
            margin-top: 8px;
        }

        .total-line.grand-total .total-label {
            font-weight: 600;
            color: #111827;
            font-size: 13px;
        }

        .total-line.grand-total .total-value {
            font-weight: 700;
            color: #111827;
            font-size: 16px;
        }

        /* Footer */
        .footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .footer-content {
            display: table;
            width: 100%;
        }

        .footer-section {
            display: table-cell;
            width: 33.33%;
            vertical-align: top;
        }

        .footer h4 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .footer p {
            font-size: 10px;
            color: #6b7280;
            line-height: 1.6;
        }

        /* Notes */
        .notes {
            margin-top: 30px;
            padding: 16px;
            background-color: #f9fafb;
            border-radius: 6px;
        }

        .notes h4 {
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .notes p {
            font-size: 10px;
            color: #6b7280;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="logo-section">
                    <h1>Host UK</h1>
                    <p>Hosting and SaaS for UK businesses</p>
                </div>
                <div class="invoice-info">
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                    <div class="invoice-date">
                        Issued: {{ $invoice->issued_at?->format('j F Y') ?? $invoice->created_at->format('j F Y') }}
                        @if($invoice->due_date)
                            <br>Due: {{ $invoice->due_date->format('j F Y') }}
                        @endif
                    </div>
                    @if($invoice->isPaid())
                        <span class="status-badge status-paid">Paid</span>
                    @elseif($invoice->isOverdue())
                        <span class="status-badge status-overdue">Overdue</span>
                    @else
                        <span class="status-badge status-pending">Pending</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Addresses -->
        <div class="addresses">
            <div class="address-box">
                <h3>From</h3>
                <p>
                    <strong>{{ $business['name'] ?? 'Host UK Ltd' }}</strong><br>
                    {{ $business['address_line1'] ?? '' }}<br>
                    @if(isset($business['address_line2']) && $business['address_line2'])
                        {{ $business['address_line2'] }}<br>
                    @endif
                    {{ $business['city'] ?? '' }}, {{ $business['postcode'] ?? '' }}<br>
                    {{ $business['country'] ?? 'United Kingdom' }}
                    @if(isset($business['vat_number']) && $business['vat_number'])
                        <br><br>VAT: {{ $business['vat_number'] }}
                    @endif
                </p>
            </div>
            <div class="address-box">
                <h3>Bill To</h3>
                <p>
                    <strong>{{ $invoice->billing_name }}</strong><br>
                    {{ $invoice->billing_email }}<br>
                    @if($invoice->billing_address)
                        @if(is_array($invoice->billing_address))
                            @if(isset($invoice->billing_address['line1'])){{ $invoice->billing_address['line1'] }}<br>@endif
                            @if(isset($invoice->billing_address['line2']) && $invoice->billing_address['line2']){{ $invoice->billing_address['line2'] }}<br>@endif
                            @if(isset($invoice->billing_address['city'])){{ $invoice->billing_address['city'] }}, @endif
                            @if(isset($invoice->billing_address['postcode'])){{ $invoice->billing_address['postcode'] }}<br>@endif
                            @if(isset($invoice->billing_address['country'])){{ $invoice->billing_address['country'] }}@endif
                        @else
                            {{ $invoice->billing_address }}
                        @endif
                    @endif
                    @if($invoice->workspace?->billing_vat_number)
                        <br><br>VAT: {{ $invoice->workspace->billing_vat_number }}
                    @endif
                </p>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%">Description</th>
                    <th style="width: 15%">Qty</th>
                    <th style="width: 15%">Unit Price</th>
                    <th style="width: 20%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>
                        <div class="item-name">{{ $item->name }}</div>
                        @if($item->description)
                            <div class="item-description">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="item-quantity">{{ $item->quantity }}</td>
                    <td class="item-price">{{ app(\Core\Mod\Commerce\Services\CommerceService::class)->formatMoney($item->unit_price, $invoice->currency) }}</td>
                    <td class="item-price">{{ app(\Core\Mod\Commerce\Services\CommerceService::class)->formatMoney($item->total, $invoice->currency) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="totals-row">
                <div class="totals-spacer"></div>
                <div class="totals-content">
                    <div class="total-line">
                        <span class="total-label">Subtotal</span>
                        <span class="total-value">{{ app(\Core\Mod\Commerce\Services\CommerceService::class)->formatMoney($invoice->subtotal, $invoice->currency) }}</span>
                    </div>
                    @if($invoice->discount_amount > 0)
                    <div class="total-line">
                        <span class="total-label">Discount</span>
                        <span class="total-value">-{{ app(\Core\Mod\Commerce\Services\CommerceService::class)->formatMoney($invoice->discount_amount, $invoice->currency) }}</span>
                    </div>
                    @endif
                    @if($invoice->tax_amount > 0)
                    <div class="total-line">
                        <span class="total-label">
                            @if($invoice->tax_rate)
                                VAT ({{ number_format($invoice->tax_rate, 0) }}%)
                            @else
                                VAT
                            @endif
                        </span>
                        <span class="total-value">{{ app(\Core\Mod\Commerce\Services\CommerceService::class)->formatMoney($invoice->tax_amount, $invoice->currency) }}</span>
                    </div>
                    @endif
                    <div class="total-line grand-total">
                        <span class="total-label">Total</span>
                        <span class="total-value">{{ app(\Core\Mod\Commerce\Services\CommerceService::class)->formatMoney($invoice->total, $invoice->currency) }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($invoice->isPaid())
        <div class="notes">
            <h4>Payment Received</h4>
            <p>
                Thank you for your payment. This invoice was paid on {{ $invoice->paid_at?->format('j F Y') ?? 'N/A' }}.
            </p>
        </div>
        @else
        <div class="notes">
            <h4>Payment Information</h4>
            <p>
                Please ensure payment is received by the due date. Payment can be made via our secure checkout at host.uk.com.
                For any questions regarding this invoice, please contact support@host.uk.com.
            </p>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p>
                        support@host.uk.com<br>
                        host.uk.com
                    </p>
                </div>
                <div class="footer-section">
                    <h4>Company Details</h4>
                    <p>
                        {{ $business['name'] ?? 'Host UK Ltd' }}<br>
                        @if(isset($business['company_number']))
                            Company No: {{ $business['company_number'] }}<br>
                        @endif
                        @if(isset($business['vat_number']))
                            VAT No: {{ $business['vat_number'] }}
                        @endif
                    </p>
                </div>
                <div class="footer-section" style="text-align: right;">
                    <p style="color: #9ca3af;">
                        Generated on {{ now()->format('j F Y') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

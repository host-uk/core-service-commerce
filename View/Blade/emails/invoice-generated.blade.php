<x-mail::message>
@if($isPaid)
# Invoice Payment Received

Thank you for your payment. Your invoice has been processed successfully.
@else
# Invoice Requires Payment

A new invoice has been generated for your account. Please review the details below.
@endif

**Invoice Number:** {{ $invoice->invoice_number }}<br>
**Issue Date:** {{ $invoice->issue_date->format('j F Y') }}<br>
@if(!$isPaid)
**Due Date:** {{ $invoice->due_date->format('j F Y') }}
@endif

---

## Invoice Summary

@foreach($items as $item)
- {{ $item->description }} — £{{ number_format($item->line_total, 2) }}
@endforeach

@if($invoice->discount_amount > 0)
**Discount:** -£{{ number_format($invoice->discount_amount, 2) }}<br>
@endif
@if($invoice->tax_amount > 0)
**VAT ({{ $invoice->tax_rate }}%):** £{{ number_format($invoice->tax_amount, 2) }}<br>
@endif
**Total:** £{{ number_format($invoice->total, 2) }}

@if($isPaid)
**Status:** Paid on {{ $invoice->paid_at->format('j F Y') }}
@else
**Amount Due:** £{{ number_format($invoice->amount_due, 2) }}
@endif

---

<x-mail::button :url="$viewUrl">
View Invoice
</x-mail::button>

@if(!$isPaid)
Please ensure payment is received by the due date to avoid any service interruption.

If you have any questions about this invoice, please contact us at [billing@host.uk.com](mailto:billing@host.uk.com).
@endif

Thanks,<br>
{{ config('app.name') }}

<x-mail::subcopy>
View invoice online: {{ $viewUrl }}<br>
Download PDF: {{ $downloadUrl }}
</x-mail::subcopy>
</x-mail::message>

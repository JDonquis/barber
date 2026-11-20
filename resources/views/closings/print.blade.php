<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $closing->barber_id !== null ? 'Liquidación de '.($closing->barber->name ?? 'Barbero') : 'Cierre '.ucfirst($closing->period_type) }} · {{ $shopName }}</title>
    <style>
        :root {
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --soft: #f8fafc;
            --accent: #c01c15;
            --emerald: #047857;
        }

        * { box-sizing: border-box; }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            color: var(--text);
            background: #f1f5f9;
            margin: 0;
            padding: 24px 16px;
            font-size: 13px;
            line-height: 1.5;
        }

        .sheet {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 32px;
        }

        .toolbar { max-width: 820px; margin: 0 auto 14px; display: flex; justify-content: flex-end; }

        button {
            padding: 9px 18px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }

        button:hover { background: var(--soft); }

        .muted { color: var(--muted); margin: 0; }

        /* Header */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--text);
        }

        .doc-header h1 { font-size: 20px; margin: 0 0 4px; letter-spacing: -0.01em; }

        .brand { display: flex; align-items: center; gap: 14px; }

        .brand-logo {
            width: 58px;
            height: 58px;
            object-fit: contain;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--soft);
            padding: 4px;
            flex-shrink: 0;
        }

        .doc-meta { text-align: right; }

        .doc-meta .doc-type {
            display: inline-block;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .status {
            display: inline-block;
            margin-top: 8px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border: 1px solid currentColor;
        }

        .status.closed { color: var(--emerald); }
        .status.open { color: var(--accent); }

        /* KPI cards */
        .kpis {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 22px 0;
        }

        .kpi {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
        }

        .kpi .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
        .kpi .value { font-size: 18px; font-weight: 700; margin-top: 4px; }
        .kpi .hint { font-size: 10px; color: var(--muted); margin-top: 2px; }

        .kpi.highlight { background: var(--soft); border-color: #cbd5e1; }
        .kpi.highlight .value { color: var(--emerald); }

        /* Sections */
        section { margin-top: 22px; }

        h2 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin: 0 0 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--border);
        }

        .section-head { display: flex; justify-content: space-between; align-items: baseline; }
        .section-head span { font-size: 11px; color: var(--muted); }

        table { width: 100%; border-collapse: collapse; }

        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); }
        th { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); font-weight: 600; }
        td { font-size: 13px; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:nth-child(even) { background: var(--soft); }

        .text-right { text-align: right; }
        .strong { font-weight: 700; }
        .accent { color: var(--accent); font-weight: 700; }
        .emerald { color: var(--emerald); font-weight: 700; }

        .empty { color: var(--muted); text-align: center; padding: 16px; font-size: 12px; }

        /* Summary key/value */
        .kv td { border-bottom: 1px solid var(--border); }
        .kv tr:nth-child(even) { background: transparent; }

        .totals {
            margin-top: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }

        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
        }

        .totals .row:last-child { border-bottom: none; }
        .totals .row.main { background: var(--soft); }
        .totals .row .amount { font-weight: 700; }
        .totals .row .amount.big { font-size: 18px; color: var(--accent); }

        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
        .two-col > section:first-child { padding-right: 24px; }
        .two-col > section + section { border-left: 1px solid var(--border); padding-left: 24px; }

        /* Footer */
        .doc-footer {
            margin-top: 28px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-size: 11px;
            color: var(--muted);
        }

        @media (max-width: 640px) {
            .doc-header { flex-direction: column; }
            .doc-meta { text-align: left; }
            .kpis { grid-template-columns: 1fr 1fr; }
            .two-col { grid-template-columns: 1fr; }
            .two-col > section:first-child { padding-right: 0; }
            .two-col > section + section { border-left: none; padding-left: 0; }
        }

        @media print {
            body { background: #fff; padding: 0; font-size: 12px; }
            .sheet { border: none; border-radius: 0; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            section { break-inside: avoid; }
            .kpi, .totals { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button onclick="window.print()">Imprimir</button>
    </div>

    <div class="sheet">
        <!-- Encabezado -->
        <header class="doc-header">
            <div class="brand">
                <img src="{{ $companyLogo }}" alt="{{ $shopName }}" class="brand-logo">
                <div>
                    <h1>{{ $shopName }}</h1>
                    <p class="muted">
                        @if (setting('shop_rif')) RIF: {{ setting('shop_rif') }}<br>@endif
                        @if (setting('shop_phone')) Tel: {{ setting('shop_phone') }}<br>@endif
                        @if (setting('shop_address')) {{ setting('shop_address') }}@endif
                    </p>
                </div>
            </div>
            <div class="doc-meta">
                <span class="doc-type">
                    {{ $closing->barber_id !== null ? 'Liquidación · '.($closing->barber->name ?? 'Barbero') : 'Cierre '.ucfirst($closing->period_type) }}
                </span>
                <p class="muted">
                    @if ($closing->barber_id !== null) {{ $closing->barber->name ?? 'Barbero' }} · @endif{{ $closing->period_start->format('d/m/Y') }} — {{ $closing->period_end->format('d/m/Y') }}<br>
                    Emitido: {{ now()->format('d/m/Y h:i a') }}
                </p>
                <span class="status {{ $closing->isClosed() ? 'closed' : 'open' }}">
                    {{ $closing->isClosed() ? 'Cerrado' : 'Abierto' }}
                </span>
            </div>
        </header>

        @php
            $services = collect($closing->details['servicios'] ?? []);
            $products = collect($closing->details['productos'] ?? []);
            $expenses = collect($closing->details['gastos'] ?? []);
            $retentions = collect($closing->details['retenciones'] ?? []);
            $loans = collect($closing->details['prestamos'] ?? []);
            $loanTotals = $closing->details['prestamos_totales'] ?? [
                'issued_usd' => 0,
                'paid_usd' => 0,
                'outstanding_usd' => 0,
                'count' => $loans->count(),
            ];
            $barbers = collect($closing->details['barberos'] ?? [])
                ->reject(fn ($row) => in_array($row['name'] ?? '', ['Sin barbero', 'Venta tienda'], true))
                ->values();
            $isBarberClosing = $closing->barber_id !== null;
            $barberName = $closing->barber->name ?? 'Barbero';
            $serviceQty = (int) $services->sum('quantity');
            $productQty = (int) $products->sum('quantity');
            $barberEarnings = $closing->barberEarningsUsd();
            $reference = $closing->total_ves_reference > 0
                ? $closing->total_ves_reference
                : to_ves($closing->total_usd, (float) $closing->exchange_rate);
        @endphp

        <!-- KPIs -->
        <div class="kpis">
            <div class="kpi">
                <div class="label">Servicios</div>
                <div class="value">{{ money($closing->total_services_usd) }}</div>
                <div class="hint">{{ $serviceQty }} atenciones</div>
            </div>
            <div class="kpi">
                <div class="label">Productos</div>
                <div class="value">{{ money($closing->total_products_usd) }}</div>
                <div class="hint">{{ $productQty }} artículos</div>
            </div>
            @if ($isBarberClosing)
                <div class="kpi">
                    <div class="label">Producción</div>
                    <div class="value">{{ money((float) $closing->total_services_usd + (float) $closing->total_products_usd) }}</div>
                    <div class="hint">{{ $closing->ticket_count }} tickets</div>
                </div>
                <div class="kpi highlight">
                    <div class="label">A pagar</div>
                    <div class="value">{{ money($barberEarnings) }}</div>
                    <div class="hint">Comisión + propinas − retenciones de {{ $barberName }}</div>
                </div>
            @else
                <div class="kpi">
                    <div class="label">Comisiones + propinas − ret.</div>
                    <div class="value">{{ money($barberEarnings) }}</div>
                    <div class="hint">A liquidar staff · Ret. {{ money($closing->barber_retentions_usd) }}</div>
                </div>
                <div class="kpi">
                    <div class="label">Gastos</div>
                    <div class="value">{{ money($closing->total_expenses_usd) }}</div>
                    <div class="hint">{{ $expenses->count() }} egresos</div>
                </div>
                <div class="kpi highlight">
                    <div class="label">Monto tienda</div>
                    <div class="value">{{ money($closing->shop_amount_usd) }}</div>
                    <div class="hint">Ventas − comisiones − propinas + retenciones − gastos</div>
                </div>
            @endif
        </div>

        <!-- Resumen del período -->
        <section>
            <div class="section-head">
                <h2>Resumen del período</h2>
                <span>{{ ucfirst($closing->period_type) }}</span>
            </div>
            <table class="kv">
                <tbody>
                    <tr>
                        <td class="muted">Rango de fechas</td>
                        <td class="text-right strong">{{ $closing->period_start->format('d/m/Y') }} — {{ $closing->period_end->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Total operaciones</td>
                        <td class="text-right strong">{{ $closing->ticket_count }} tickets</td>
                    </tr>
                    @if ($usdConversionEnabled)
                        <tr>
                            <td class="muted">Tasa promedio del período</td>
                            <td class="text-right strong">{{ local_currency_symbol() }} {{ number_format($closing->average_rate ?: $closing->exchange_rate, 2, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Tasa de cierre</td>
                            <td class="text-right strong">{{ local_currency_symbol() }} {{ number_format($closing->exchange_rate, 2, ',', '.') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <div class="totals">
                <div class="row main">
                    <span class="muted">Total recaudado {{ $usdConversionEnabled ? '(USD)' : '('.local_currency().')' }}</span>
                    <span class="amount big">{{ money($closing->total_usd, $closing->localTotal()) }}</span>
                </div>
                @if ($usdConversionEnabled)
                    <div class="row">
                        <span class="muted">{{ local_currency() }} cobrados (tasa histórica del período)</span>
                        <span class="amount emerald">{{ ves($closing->total_ves) }}</span>
                    </div>
                    <div class="row">
                        <span class="muted">Equivalente a tasa de cierre</span>
                        <span class="amount">{{ ves($reference) }}</span>
                    </div>
                @endif
                <div class="row">
                    <span class="muted">{{ $isBarberClosing ? 'Comisión' : 'Comisiones barberos' }}</span>
                    <span class="amount">- {{ money($closing->barber_commission_usd) }}</span>
                </div>
                <div class="row">
                    <span class="muted">{{ $isBarberClosing ? 'Propinas' : 'Propinas barberos' }}</span>
                    <span class="amount">- {{ money($closing->barber_tips_usd) }}</span>
                </div>
                <div class="row">
                    <span class="muted">{{ $isBarberClosing ? 'Retenciones' : 'Retenciones barberos' }}</span>
                    <span class="amount">- {{ money($closing->barber_retentions_usd) }}</span>
                </div>
                @if ($isBarberClosing)
                    <div class="row main">
                        <span class="strong">Ganancia total</span>
                        <span class="amount emerald">{{ money($barberEarnings) }}</span>
                    </div>
                @endif
                @unless ($isBarberClosing)
                    <div class="row">
                        <span class="muted">Gastos del período</span>
                        <span class="amount">- {{ money($closing->total_expenses_usd) }}</span>
                    </div>
                @endunless
                <div class="row main">
                    <span class="strong">{{ $isBarberClosing ? 'Saldo para el estudio' : 'Ganancia neta tienda' }}</span>
                    <span class="amount emerald">{{ money($closing->shop_amount_usd) }}</span>
                </div>
            </div>
        </section>

        @if ($loans->isNotEmpty())
            <section>
                <div class="section-head">
                    <h2>Préstamos del período</h2>
                    <span>{{ $loanTotals['count'] ?? $loans->count() }} préstamo(s)</span>
                </div>
                <table class="kv">
                    <tbody>
                        <tr>
                            <td class="muted">Prestado en el período</td>
                            <td class="text-right strong">{{ money($loanTotals['issued_usd'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Abonos recibidos en el período</td>
                            <td class="text-right strong emerald">{{ money($loanTotals['paid_usd'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Saldo pendiente por cobrar</td>
                            <td class="text-right strong">{{ money($loanTotals['outstanding_usd'] ?? 0) }}</td>
                        </tr>
                    </tbody>
                </table>

                <table>
                    <thead>
                        <tr><th>Barbero / fecha</th><th class="text-right">Prestado</th><th class="text-right">Pagado</th><th class="text-right">Saldo</th><th class="text-right">Avance</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($loans as $loan)
                            <tr>
                                <td>
                                    {{ $loan['barber_name'] ?? 'Barbero' }}
                                    <div class="item-unit">{{ $loan['loan_date'] ?? '' }}@unless ($loan['loaned_in_period'] ?? true) · anterior @endunless</div>
                                </td>
                                <td class="text-right">{{ money($loan['amount_usd'] ?? 0) }}</td>
                                <td class="text-right">{{ money($loan['paid_usd'] ?? 0) }}</td>
                                <td class="text-right strong">{{ money($loan['balance_usd'] ?? 0) }}</td>
                                <td class="text-right">{{ (int) ($loan['progress'] ?? 0) }}%</td>
                            </tr>
                            @if (! empty($loan['payments']))
                                <tr>
                                    <td colspan="5" class="item-unit">
                                        Abonos del período:
                                        @foreach ($loan['payments'] as $payment)
                                            {{ $payment['date'] ?? '' }} {{ money($payment['amount_usd'] ?? 0) }}@if (! empty($payment['payment_method'])) ({{ $payment['payment_method'] }})@endif{{ ! $loop->last ? ' · ' : '' }}
                                        @endforeach
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if ($retentions->isNotEmpty())
            <section>
                <div class="section-head">
                    <h2>Retenciones aplicadas</h2>
                    <span>{{ $retentions->count() }} concepto(s)</span>
                </div>
                <table>
                    <thead>
                        <tr><th>Concepto</th><th>Frecuencia</th><th class="text-right">Aplicado</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($retentions as $row)
                            <tr>
                                <td>
                                    {{ $row['name'] }}
                                    @unless ($isBarberClosing)
                                        <div class="item-unit">{{ $row['barber_name'] ?? 'Barbero' }}</div>
                                    @endunless
                                </td>
                                <td>{{ $row['frequency_label'] ?? '' }}</td>
                                <td class="text-right strong">- {{ money($row['applied_usd'] ?? 0, $row['applied_local'] ?? null) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <!-- Servicios / productos vendidos -->
        <div class="two-col">
            <section>
                <div class="section-head">
                    <h2>Servicios vendidos</h2>
                    <span>{{ $services->count() }} ítems</span>
                </div>
                <table>
                    <thead>
                        <tr><th>Servicio</th><th class="text-right">Cant.</th><th class="text-right">Total</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($services as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">{{ $row['quantity'] }}</td>
                                <td class="text-right strong">{{ money($row['total_usd']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Sin servicios.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section>
                <div class="section-head">
                    <h2>Productos vendidos</h2>
                    <span>{{ $products->count() }} ítems</span>
                </div>
                <table>
                    <thead>
                        <tr><th>Producto</th><th class="text-right">Cant.</th><th class="text-right">Total</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">{{ $row['quantity'] }}</td>
                                <td class="text-right strong">{{ money($row['total_usd']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Sin productos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <!-- Por barbero / método de pago -->
        <div class="two-col">
            @unless ($isBarberClosing)
            <section>
                <div class="section-head">
                    <h2>Por barbero</h2>
                    <span>{{ $barbers->count() }} responsables</span>
                </div>
                <table>
                    <thead>
                        <tr><th>Barbero</th><th class="text-right">Tickets</th><th class="text-right">Comisión</th><th class="text-right">Propina</th><th class="text-right">Retención</th><th class="text-right">Ganancia</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($barbers as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">{{ $row['tickets'] }}</td>
                                <td class="text-right">{{ money($row['commission_usd']) }}</td>
                                <td class="text-right">{{ money($row['tip_usd'] ?? 0) }}</td>
                                <td class="text-right">{{ money($row['retentions_usd'] ?? 0) }}</td>
                                <td class="text-right strong">{{ money($row['earnings_usd'] ?? ($row['commission_usd'] + ($row['tip_usd'] ?? 0))) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
            @endunless

            <section>
                <div class="section-head">
                    <h2>Por método de pago</h2>
                    <span>{{ count($closing->details['metodos_pago'] ?? []) }} vías</span>
                </div>
                <table>
                    <thead>
                        <tr><th>Método</th><th class="text-right">Tickets</th><th class="text-right">Total</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($closing->details['metodos_pago'] ?? [] as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">{{ $row['count'] }}</td>
                                <td class="text-right strong">{{ money($row['total_usd']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <!-- Gastos del período -->
        @unless ($isBarberClosing)
        <section>
            <div class="section-head">
                <h2>Gastos registrados</h2>
                <span>{{ $expenses->count() }} registros</span>
            </div>
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Concepto</th><th>Nota</th><th class="text-right">Monto</th></tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['concept'] }}</td>
                            <td class="muted">{{ $row['notes'] ?? '—' }}</td>
                            <td class="text-right strong">- {{ money($row['amount_usd']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Sin gastos registrados en este período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        @endunless

        @if ($closing->notes)
            <section>
                <h2>Notas</h2>
                <p>{{ $closing->notes }}</p>
            </section>
        @endif

        <footer class="doc-footer">
            <div>
                @if ($closing->closed_at)
                    <p class="muted">Cerrado por <strong>{{ $closing->closedBy->name ?? '—' }}</strong> el {{ $closing->closed_at->format('d/m/Y h:i a') }}.</p>
                @endif
                @if ($closing->reopened_at)
                    <p class="muted">Reabierto por <strong>{{ $closing->reopenedBy->name ?? '—' }}</strong> el {{ $closing->reopened_at->format('d/m/Y h:i a') }}.</p>
                @endif
            </div>
            <div class="muted">{{ $shopName }} · Cierre {{ ucfirst($closing->period_type) }}</div>
        </footer>
    </div>
</body>
</html>

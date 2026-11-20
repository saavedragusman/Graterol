{{-- BR-02: tasa BCV vigente en el menú lateral (d-dashboard.html). Valores ya formateados por el panel. --}}
<div class="erp-sidebar-rate">
    <span class="erp-sidebar-rate__label">{{ $label }}</span>
    <strong class="erp-sidebar-rate__value">{{ $value }}</strong>
    @if ($detail !== null)
        <span class="erp-sidebar-rate__label">{{ $detail }}</span>
    @endif
</div>

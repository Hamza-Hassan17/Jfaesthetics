<style>
    .jfr-title { color: #0a3535; font-weight: 800; }
    .jfr-filter-card, .jfr-table-card, .jfr-panel {
        background: #fff;
        border: 1px solid #e9eef0;
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(10, 53, 53, 0.05);
    }
    .jfr-filter-card { padding: 22px; margin-bottom: 24px; }
    .jfr-panel { padding: 20px 22px; margin-bottom: 24px; }
    .jfr-table-card { padding: 6px 6px 14px; margin-bottom: 24px; }
    .jfr-filter-card .form-control { border-radius: 8px; border-color: #e1e7e7; }
    .jfr-filter-card .form-control:focus {
        border-color: #148080;
        box-shadow: 0 0 0 3px rgba(20, 128, 128, 0.12);
    }
    .btn-jfr-teal { background: #148080; border-color: #148080; color: #fff; }
    .btn-jfr-teal:hover { background: #0d5c5c; border-color: #0d5c5c; color: #fff; }
    .jfr-table thead th {
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: .04em;
        color: #7a8a8a;
        background: #f4f8f8;
        border-bottom: none;
        white-space: nowrap;
    }
    .jfr-table td { vertical-align: middle; font-size: 13.5px; }
    .jfr-table tbody tr:hover { background: rgba(20, 128, 128, .04); }
    .jfr-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 30px;
        font-size: 11px;
        font-weight: 700;
        margin-right: 3px;
    }
    .jfr-badge-ok { background: rgba(56, 176, 105, .12); color: #2f9c5c; }
    .jfr-badge-low { background: rgba(230, 140, 40, .14); color: #d47f1f; }
    .jfr-badge-negative { background: rgba(224, 83, 83, .14); color: #d43f3f; }
    .jfr-badge-expiring { background: rgba(240, 190, 40, .2); color: #a07800; }
    .jfr-badge-muted { background: #eef2f2; color: #7a8a8a; }
    .jfr-icon-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid transparent;
        margin-right: 4px;
    }
    .jfr-icon-btn.view { background: rgba(33, 150, 243, .1); color: #2196F3; }
    .jfr-icon-btn.edit { background: rgba(230, 140, 40, .1); color: #d47f1f; }
    .jfr-icon-btn.delete { background: rgba(224, 83, 83, .1); color: #d43f3f; }
    .jfr-entries-info { color: #7a8a8a; font-size: 13px; margin-top: 10px; }
    .jfr-qty-negative { color: #d43f3f; font-weight: 800; }
    .jfr-tabs { border-bottom: 2px solid #e9eef0; margin-bottom: 22px; }
    .jfr-tabs a {
        display: inline-block;
        padding: 10px 20px;
        color: #7a8a8a;
        font-weight: 700;
        font-size: 14px;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }
    .jfr-tabs a:hover { color: #148080; text-decoration: none; }
    .jfr-tabs a.active { color: #148080; border-color: #148080; }
</style>

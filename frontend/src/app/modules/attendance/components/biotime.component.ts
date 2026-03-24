import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';

@Component({
  standalone: false,
  selector: 'app-biotime',
  templateUrl: './biotime.component.html',
  styleUrls: ['./biotime.component.scss'],
})
export class BioTimeComponent implements OnInit {

  devices:  any[] = [];
  loading   = false;
  submitting = false;

  selectedDevice: any = null;
  deviceStats:    any = null;
  statsLoading    = false;

  // Device form
  showDeviceForm = false;
  editDeviceId:  number | null = null;
  deviceFormError = '';
  deviceForm: any = this.blankDeviceForm();

  // Sync panel
  showSyncPanel = false;
  syncDevice:   any = null;
  syncForm      = { from: this.defaultFrom(), to: this.today() };
  syncing       = false;
  syncResult:   any = null;
  syncError     = '';

  // Test connection
  testingId:    number | null = null;
  testResult:   any = null;

  // Logs
  logDevice:    any = null;
  logDate       = this.today();
  logs:         any[] = [];
  logsLoading   = false;
  showLogsPanel = false;

  // Employee mapping
  empMapDevice:  any = null;
  empMapData:    any[] = [];
  empMapLoading  = false;
  showEmpMap     = false;

  // Unmatched
  unmatchedData: any[] = [];
  showUnmatched  = false;

  constructor(private http: HttpClient) {}

  ngOnInit() { this.loadDevices(); }

  // ── Devices ───────────────────────────────────────────────────────────

  loadDevices() {
    this.loading = true;
    this.http.get<any>('/api/v1/biotime/devices').subscribe({
      next: r => { this.devices = Array.isArray(r) ? r : (r?.data ?? []); this.loading = false; },
      error: () => this.loading = false,
    });
  }

  openDeviceForm(d?: any) {
    this.editDeviceId  = d?.id ?? null;
    this.deviceFormError = '';
    this.deviceForm    = d ? {
      name: d.name, protocol: d.protocol, ip_address: d.ip_address,
      port: d.port, username: d.username, password: '',
      timeout_seconds: d.timeout_seconds ?? 30,
    } : this.blankDeviceForm();
    this.showDeviceForm = true;
  }

  saveDevice() {
    if (!this.deviceForm.name || !this.deviceForm.ip_address || !this.deviceForm.username) {
      this.deviceFormError = 'Name, IP address and username are required.'; return;
    }
    if (!this.editDeviceId && !this.deviceForm.password) {
      this.deviceFormError = 'Password is required for new devices.'; return;
    }
    this.submitting = true; this.deviceFormError = '';
    const req = this.editDeviceId
      ? this.http.put<any>(`/api/v1/biotime/devices/${this.editDeviceId}`, this.deviceForm)
      : this.http.post<any>('/api/v1/biotime/devices', this.deviceForm);
    req.subscribe({
      next: () => { this.submitting = false; this.showDeviceForm = false; this.loadDevices(); },
      error: err => {
        this.submitting = false;
        const errs = err?.error?.errors;
        this.deviceFormError = errs ? Object.values(errs).flat().join(' ') : err?.error?.message || 'Save failed.';
      },
    });
  }

  deleteDevice(d: any) {
    if (!confirm(`Delete device "${d.name}"? All raw punch logs will also be deleted.`)) return;
    this.http.delete(`/api/v1/biotime/devices/${d.id}`).subscribe({ next: () => this.loadDevices() });
  }

  // ── Test connection ───────────────────────────────────────────────────

  testConnection(d: any) {
    this.testingId = d.id; this.testResult = null;
    this.http.post<any>(`/api/v1/biotime/devices/${d.id}/test`, {}).subscribe({
      next: r => { this.testingId = null; this.testResult = { id: d.id, ...r }; },
      error: err => { this.testingId = null; this.testResult = { id: d.id, ok: false, message: err?.error?.message || 'Request failed' }; },
    });
  }

  // ── Sync ──────────────────────────────────────────────────────────────

  openSyncPanel(d: any) {
    this.syncDevice = d;
    this.syncForm   = { from: this.defaultFrom(), to: this.today() };
    this.syncResult = null; this.syncError = '';
    this.showSyncPanel = true;
  }

  runSync() {
    this.syncing = true; this.syncResult = null; this.syncError = '';
    this.http.post<any>(`/api/v1/biotime/devices/${this.syncDevice.id}/sync`, this.syncForm).subscribe({
      next: r => { this.syncing = false; this.syncResult = r; this.loadDevices(); },
      error: err => { this.syncing = false; this.syncError = err?.error?.message || 'Sync failed.'; },
    });
  }

  // ── Stats ─────────────────────────────────────────────────────────────

  loadStats(d: any) {
    if (this.selectedDevice?.id === d.id && this.deviceStats) { this.selectedDevice = null; this.deviceStats = null; return; }
    this.selectedDevice = d; this.statsLoading = true; this.deviceStats = null;
    this.http.get<any>(`/api/v1/biotime/devices/${d.id}/stats`).subscribe({
      next: r => { this.deviceStats = r; this.statsLoading = false; },
      error: () => this.statsLoading = false,
    });
  }

  // ── Logs ─────────────────────────────────────────────────────────────

  openLogs(d: any) {
    this.logDevice = d; this.showLogsPanel = true; this.loadLogs();
  }

  loadLogs() {
    this.logsLoading = true;
    this.http.get<any>(`/api/v1/biotime/devices/${this.logDevice.id}/logs`, { params: { date: this.logDate } }).subscribe({
      next: r => { this.logs = r?.logs || []; this.logsLoading = false; },
      error: () => this.logsLoading = false,
    });
  }

  // ── Employee mapping ──────────────────────────────────────────────────

  openEmpMap(d: any) {
    this.empMapDevice = d; this.empMapLoading = true; this.empMapData = []; this.showEmpMap = true;
    this.http.get<any>(`/api/v1/biotime/devices/${d.id}/employees`).subscribe({
      next: r => { this.empMapData = r?.employees || []; this.empMapLoading = false; },
      error: () => this.empMapLoading = false,
    });
  }

  openUnmatched(d: any) {
    this.showUnmatched = true;
    this.http.get<any>(`/api/v1/biotime/devices/${d.id}/unmatched`).subscribe({
      next: r => this.unmatchedData = r?.unmatched || [],
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────

  private blankDeviceForm() {
    return { name:'', protocol:'http', ip_address:'', port:80, username:'admin', password:'', timeout_seconds:30 };
  }
  private today() { return new Date().toISOString().slice(0,10); }
  private defaultFrom() { const d = new Date(); d.setDate(d.getDate()-7); return d.toISOString().slice(0,10); }

  statusCls(s: string) {
    return ({ success:'badge-green', connected:'badge-green', failed:'badge-red', partial:'badge-yellow' } as any)[s] ?? 'badge-gray';
  }

  punchLabel(t: number): string {
    const map: any = { 0:'Check In', 1:'Check Out', 2:'Break Out', 3:'Break In', 4:'OT In', 5:'OT Out' };
    return map[t] ?? `Type ${t}`;
  }

  verifyIcon(m: string): string {
    const map: any = { finger:'fingerprint', face:'face', card:'credit_card', pin:'pin', unknown:'help_outline' };
    return map[m?.toLowerCase()] ?? 'radio_button_unchecked';
  }
}

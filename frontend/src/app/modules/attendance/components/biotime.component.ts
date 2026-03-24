import { Component, ChangeDetectionStrategy } from '@angular/core';
import { HttpClient } from '@angular/common/http';

/**
 * BioTime integration component.
 *
 * Manages biometric device connections and imports attendance
 * records from BioTime / ZKTeco hardware into the HRMS.
 *
 * This file was scaffolded to resolve a build error caused by
 * BioTimeComponent being referenced in AttendanceModule without
 * a corresponding source file. Implement the full device
 * management UI here.
 */
@Component({
  standalone:      false,
  selector:        'app-biotime',
  templateUrl:     './biotime.component.html',
  styleUrls:       ['./biotime.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BioTimeComponent {
  devices: any[] = [];
  loading = false;

  constructor(private readonly http: HttpClient) {}
}

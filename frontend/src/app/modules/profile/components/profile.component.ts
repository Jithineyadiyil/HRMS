import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';

@Component({
  standalone: false, selector: 'app-profile',
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.scss'],
})
export class ProfileComponent implements OnInit {
  user: any = null;
  loading = true;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    try { this.user = JSON.parse(localStorage.getItem('hrms_user') || 'null'); } catch {}
    this.http.get<any>('/api/v1/profile').subscribe({
      next: r => { this.user = r?.user || r || this.user; this.loading = false; },
      error: () => this.loading = false
    });
  }

  initial(n?: string) { return n?.split(' ').map((w:string) => w[0]).join('').toUpperCase().slice(0,2) || '?'; }
}

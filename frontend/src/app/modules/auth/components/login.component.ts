import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Store } from '@ngrx/store';
import { Observable } from 'rxjs';
import { login } from '../store/auth.actions';

@Component({
  standalone: false,
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss']
})
export class LoginComponent implements OnInit {
  form!: FormGroup;
  hidePassword = true;
  loading$!: Observable<boolean>;
  error$!: Observable<string | null>;

  constructor(private fb: FormBuilder, private store: Store<any>) {}

  ngOnInit() {
    this.form = this.fb.group({
      email:    ['admin@hrms.com', [Validators.required, Validators.email]],
      password: ['Admin@1234',     Validators.required]
    });
    this.loading$ = this.store.select(s => s['auth']?.loading ?? false);
    this.error$   = this.store.select(s => s['auth']?.error ?? null);
  }

  onSubmit() {
    if (this.form.valid) {
      this.store.dispatch(login({ credentials: this.form.value }));
    }
  }
}

export const environment = {
  production: false,
  // MUST be a relative path — the dev proxy (proxy.conf.json) rewrites /api → http://localhost:8000
  // An absolute URL bypasses the proxy and causes CORS errors in the browser.
  apiUrl: '/api/v1',
};

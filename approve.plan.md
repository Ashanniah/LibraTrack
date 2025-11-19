<!-- eb5edd73-ef1e-454f-996d-b04779f04a5f 106a7514-b849-42fc-a4e7-4a500e8bf4d3 -->
# Fix Borrow Request Approval

1. Inspect `backend/approve-borrow-request.php` for parameter binding issues and ensure prepared statements use the correct number/type of placeholders.
2. Update the PHP file so `bind_param` signatures match the SQL (e.g. four placeholders for the UPDATE statement) and add additional error handling if needed.
3. Re-test the approval flow via the frontend (`librarian-borrow-requests.html`) to confirm the endpoint now responds with `{ ok: true }` and the UI updates without showing the failure alert.

### Todos

- fix-bindings: Correct `bind_param` signatures in `backend/approve-borrow-request.php`
- retest-approve: Trigger approval from the UI and verify success response

### To-dos

- [x] Correct bind_param signatures in approve endpoint
- [x] Re-run approval flow to confirm success
  - Endpoint responds with `{ ok: true }` and UI shows success modal
  - Transaction wraps: create loan, decrement book quantity, update request
  - Frontend handles `ok` or `success` response shapes




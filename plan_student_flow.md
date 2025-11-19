1. **Backend student loans endpoint**
   - Add `backend/student/my-loans.php`
   - Require login; allow student + (optionally admin) roles
   - Query `loans` joined with `books` for `student_id = $_SESSION['uid']`
   - Accept filters `status` (`borrowed|returned|overdue`), `page`, `pagesize`
   - For each record compute `status_label` (`borrowed`, `returned`, `overdue`), `due_in_days`, `days_late`
   - Return `{ ok: true, items, total }`

2. **Backend student notifications endpoint**
   - Add `backend/student/notifications.php`
   - Use same auth to fetch:
     * Latest borrow requests of the student (`borrow_requests`)
     * Current loans (due soon / overdue / returned)
   - Synthesize notification entries (`type`, `title`, `message`, `timestamp`)
   - Include due-soon (due within 2 days), overdue, approval, rejection, return confirmations
   - Return sorted list (most recent first)

3. **Student History page**
   - Replace static table with JS fetch to `student/my-loans.php`
   - Show status chips (Borrowed / Returned / Overdue) using API data
   - Include “View Details” modal reflecting actual record (book, borrow/due/return dates)
   - Provide filter tabs or dropdown to show `All/Borrowed/Returned/Overdue` (client-side filtering)

4. **Student Overdue page**
   - Fetch same endpoint with `status=overdue`
   - Display KPIs: total overdue count, estimated fines (sum `days_late * fine_rate`)
   - Table columns: Book, Borrowed, Due, Days Late, Actions (contact librarian)
   - Empty state when zero overdue

5. **Student Notifications page**
   - Fetch from new notifications endpoint
   - List grouped items (maybe by date) showing icon, title, badge (e.g., Overdue, Due Soon, Approved)
   - Include toggle for email alerts (can remain UI-only)
   - Provide “Mark all as read” action (optional client-only for now)

6. **Shared student sidebar helper**
   - Move duplicated `renderStudentSidebar`/`toggleSidebar` into a reusable function (optional but recommended)
   - Ensure Student pages call helper consistently to avoid divergence.


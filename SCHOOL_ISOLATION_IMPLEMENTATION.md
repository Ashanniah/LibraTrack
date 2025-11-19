# School-Based Data Isolation Implementation

## Overview
This document describes the school-based data isolation system where librarians can only see and manage data for their assigned school, while admins have global access.

## Key Principle
**Rule**: A librarian can only see and manage data (students, loans, low stock, history, etc.) for their own assigned school. They must not see students or circulation from other schools.

## Implementation Summary

### 1. Backend Changes

#### ✅ Database Schema
- **`loans` table**: Added `school_id` column (migration script: `backend/add-school-id-to-loans.sql`)
- **`logs` table**: Added `school_id` column for school-scoped logging
- Both tables use school_id from the student's `users.school_id` when creating records

#### ✅ Updated Endpoints (School Filtering for Librarians)

1. **`list-users.php`** ✅
   - Librarians: Filter `WHERE u.school_id = librarian.school_id`
   - Admin: No filter (sees all users)

2. **`list-loans.php`** ✅
   - Librarians: Filter by school_id (from loans table or via student)
   - Admin: No filter (sees all loans)

3. **`list-borrow-requests.php`** ✅
   - Librarians: Filter `WHERE u.school_id = librarian.school_id`
   - Admin: No filter (sees all requests)

4. **`search-students-books.php`** ✅
   - Librarians: Only search students from their school
   - Admin: Can search all students

5. **`books-list.php`** ✅
   - Librarians: Borrowed counts filtered by school
   - Admin: All borrowed counts

6. **`create-loan.php`** ✅
   - Librarians: Only create loans for students in their school
   - Sets `school_id` on loan record if column exists

7. **`return-book.php`** ✅
   - Librarians: Only return loans from their school

8. **`delete-loan.php`** ✅
   - Librarians: Only delete loans from their school

9. **`approve-borrow-request.php`** ✅
   - Librarians: Only approve requests from their school
   - Sets `school_id` when creating loan

10. **`reject-borrow-request.php`** ✅
    - Librarians: Only reject requests from their school

11. **`extend-due-date.php`** ✅
    - Librarians: Only extend due dates for loans from their school

12. **`list-logs.php`** ✅
    - Librarians: Only see logs for their school (if logs table has school_id)
    - Admin: Sees all logs

### 2. Frontend Changes

#### ✅ Student Creation Form (`librarian-users.html`)
- School field is **disabled** and auto-filled with librarian's school
- Form shows: "Automatically based on librarian's school"
- Backend automatically sets `school_id` from librarian's school when creating students

#### ✅ Dashboard Stats
- All dashboard cards automatically use school-filtered data:
  - **Active Loans**: From `list-loans.php` (school-filtered)
  - **Overdue**: From `list-loans.php` (school-filtered)
  - **Students**: From `list-students.php` (school-filtered)
  - **Low Stock**: From `books-list.php` (borrowed counts school-filtered)
- Librarians see **only their school's statistics**
- Admins see **global statistics**

#### ✅ All Pages
- **Borrow/Return**: Only shows loans from librarian's school
- **Active Loans**: Only shows active loans from librarian's school
- **Overdue**: Only shows overdue loans from librarian's school
- **History**: Only shows loan history from librarian's school
- **Low Stock**: Borrowed counts are school-filtered
- **Search**: Only searches students from librarian's school

### 3. Data Flow

#### When Librarian Creates Student:
1. Frontend: School field is disabled, shows librarian's school name
2. Frontend: Form does NOT send `school_id` (it's disabled)
3. Backend (`create-student.php`): Gets librarian's `school_id` from session
4. Backend: Automatically assigns student to librarian's school
5. Result: Student is always in librarian's school

#### When Librarian Creates Loan:
1. Frontend: Search student (only shows students from librarian's school via `search-students-books.php`)
2. Backend (`create-loan.php`): Validates student is in librarian's school
3. Backend: Sets `loan.school_id = student.school_id`
4. Result: Loan is tagged with school for filtering

#### When Librarian Views Dashboard:
1. Frontend: Calls `list-loans.php`, `list-students.php`, `books-list.php`
2. Backend: Each endpoint filters by librarian's `school_id`
3. Frontend: Displays filtered stats
4. Result: All cards show only librarian's school data

### 4. Admin Behavior (No Filtering)
- Admins see **ALL schools** globally
- No school filtering applied to any endpoint
- Admin dashboard shows totals across all schools
- Admin can manage librarians and assign them to schools

## Migration Steps

1. **Run Migration Script**:
   ```
   Visit: http://localhost/libratrack/backend/migrate-school-isolation.php
   ```
   This will:
   - Add `school_id` column to `loans` table
   - Populate existing loans with school_id from students
   - Add `school_id` column to `logs` table

2. **Verify Librarians Have Schools**:
   - Ensure all librarians have a `school_id` assigned
   - Admin can assign schools via the Users management page

3. **Test School Isolation**:
   - Log in as a librarian
   - Verify they only see their school's students, loans, etc.
   - Verify they cannot create loans for students from other schools

## Security Notes

- All school filtering is done **server-side** (backend)
- Frontend cannot bypass school filtering
- School validation happens on:
  - Read operations (list, search)
  - Write operations (create, update, delete)
  - All loan operations (borrow, return, extend, delete)

## Files Modified

### Backend:
- `list-users.php`
- `list-loans.php`
- `list-borrow-requests.php`
- `search-students-books.php`
- `books-list.php`
- `create-loan.php`
- `return-book.php`
- `delete-loan.php`
- `approve-borrow-request.php`
- `reject-borrow-request.php`
- `extend-due-date.php`
- `list-logs.php`
- `auth.php` (already had school_id support)

### Frontend:
- `librarian-users.html` (school field already disabled)
- `dashboard.html` (uses filtered endpoints)
- All other pages automatically benefit from filtered endpoints

### Migration:
- `backend/add-school-id-to-loans.sql` (manual SQL)
- `backend/migrate-school-isolation.php` (automated migration script)










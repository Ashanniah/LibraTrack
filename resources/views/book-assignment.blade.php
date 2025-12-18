<!DOCTYPE html>
<html>
<head>
    <title>Assign All Books to Librarian</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffebee; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: #1976d2; background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f57c00; background: #fff3e0; padding: 15px; border-radius: 5px; margin: 10px 0; }
        button { background: #4CAF50; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .count { font-size: 24px; font-weight: bold; color: #4CAF50; }
    </style>
</head>
<body>
    <h1>📚 Assign All Unassigned Books to Current Librarian</h1>
    
    <div class="info">
        <strong>Current User:</strong><br>
        ID: {{ $user['id'] }}<br>
        Name: {{ $user['first_name'] }} {{ $user['last_name'] }}<br>
        Role: {{ $user['role'] }}<br>
        Email: {{ $user['email'] }}
    </div>
    
    @if(strtolower($user['role'] ?? '') !== 'librarian')
        <div class="warning">⚠️ Warning: Current user is not a librarian (role: {{ $user['role'] }})</div>
        <div class="info">You can still assign books, but they will be assigned to this user account.</div>
    @endif
    
    <div class="info">
        <strong>Book Statistics:</strong><br>
        Total books: <span class="count">{{ $totalBooks }}</span><br>
        Unassigned books (added_by = NULL): <span class="count">{{ $unassignedCount }}</span><br>
        Books assigned to you: <span class="count">{{ $myBooksCount }}</span>
    </div>
    
    @if($success)
        <div class="success">
            ✓ Successfully assigned <strong>{{ $assigned }} book(s)</strong> to {{ $user['first_name'] }} {{ $user['last_name'] }}!<br>
            All books should now be visible in your book list.
        </div>
        <div class="info">
            <a href="/books.html" style="color: #1976d2; text-decoration: underline;">→ Go to Books Page</a>
        </div>
    @endif
    
    @if($unassignedCount > 0)
        <div class="warning">
            <strong>⚠️ {{ $unassignedCount }} book(s) are unassigned.</strong><br>
            These books won't show up for any librarian because they have <code>added_by = NULL</code>.<br>
            Click the button below to assign all unassigned books to your account.
        </div>
        
        <form method="POST" onsubmit="return confirm('Are you sure you want to assign ALL {{ $unassignedCount }} unassigned books to your account? This cannot be undone easily.');">
            @csrf
            <input type="hidden" name="assign_all" value="yes">
            <button type="submit">Assign All {{ $unassignedCount }} Unassigned Books to Me</button>
        </form>
        
        <h2>Unassigned Books</h2>
        <table>
            <tr><th>ID</th><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Created</th></tr>
            @foreach($unassignedBooks as $book)
                <tr>
                    <td>{{ $book->id }}</td>
                    <td>{{ $book->title }}</td>
                    <td>{{ $book->author }}</td>
                    <td>{{ $book->isbn }}</td>
                    <td>{{ $book->category }}</td>
                    <td>{{ $book->created_at }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <div class="success">✓ All books are assigned! No unassigned books found.</div>
    @endif
    
    @if(count($myBooks) > 0)
        <h2>My Books ({{ count($myBooks) }})</h2>
        <p>These are the books assigned to your account. They should be visible in your book list.</p>
        <table>
            <tr><th>ID</th><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Created</th></tr>
            @foreach($myBooks as $book)
                <tr>
                    <td>{{ $book->id }}</td>
                    <td>{{ $book->title }}</td>
                    <td>{{ $book->author }}</td>
                    <td>{{ $book->isbn }}</td>
                    <td>{{ $book->category }}</td>
                    <td>{{ $book->created_at }}</td>
                </tr>
            @endforeach
        </table>
    @endif
    
    <hr>
    <div class="info">
        <strong>📝 Note:</strong> After assigning books, refresh your <a href="/books.html">Books page</a> to see them.<br>
        New books you add will automatically be assigned to your account.
    </div>
</body>
</html>



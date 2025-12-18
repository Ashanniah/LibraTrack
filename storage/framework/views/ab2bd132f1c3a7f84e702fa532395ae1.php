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
        ID: <?php echo e($user['id']); ?><br>
        Name: <?php echo e($user['first_name']); ?> <?php echo e($user['last_name']); ?><br>
        Role: <?php echo e($user['role']); ?><br>
        Email: <?php echo e($user['email']); ?>

    </div>
    
    <?php if(strtolower($user['role'] ?? '') !== 'librarian'): ?>
        <div class="warning">⚠️ Warning: Current user is not a librarian (role: <?php echo e($user['role']); ?>)</div>
        <div class="info">You can still assign books, but they will be assigned to this user account.</div>
    <?php endif; ?>
    
    <div class="info">
        <strong>Book Statistics:</strong><br>
        Total books: <span class="count"><?php echo e($totalBooks); ?></span><br>
        Unassigned books (added_by = NULL): <span class="count"><?php echo e($unassignedCount); ?></span><br>
        Books assigned to you: <span class="count"><?php echo e($myBooksCount); ?></span>
    </div>
    
    <?php if($success): ?>
        <div class="success">
            ✓ Successfully assigned <strong><?php echo e($assigned); ?> book(s)</strong> to <?php echo e($user['first_name']); ?> <?php echo e($user['last_name']); ?>!<br>
            All books should now be visible in your book list.
        </div>
        <div class="info">
            <a href="/books.html" style="color: #1976d2; text-decoration: underline;">→ Go to Books Page</a>
        </div>
    <?php endif; ?>
    
    <?php if($unassignedCount > 0): ?>
        <div class="warning">
            <strong>⚠️ <?php echo e($unassignedCount); ?> book(s) are unassigned.</strong><br>
            These books won't show up for any librarian because they have <code>added_by = NULL</code>.<br>
            Click the button below to assign all unassigned books to your account.
        </div>
        
        <form method="POST" onsubmit="return confirm('Are you sure you want to assign ALL <?php echo e($unassignedCount); ?> unassigned books to your account? This cannot be undone easily.');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="assign_all" value="yes">
            <button type="submit">Assign All <?php echo e($unassignedCount); ?> Unassigned Books to Me</button>
        </form>
        
        <h2>Unassigned Books</h2>
        <table>
            <tr><th>ID</th><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Created</th></tr>
            <?php $__currentLoopData = $unassignedBooks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $book): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($book->id); ?></td>
                    <td><?php echo e($book->title); ?></td>
                    <td><?php echo e($book->author); ?></td>
                    <td><?php echo e($book->isbn); ?></td>
                    <td><?php echo e($book->category); ?></td>
                    <td><?php echo e($book->created_at); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
    <?php else: ?>
        <div class="success">✓ All books are assigned! No unassigned books found.</div>
    <?php endif; ?>
    
    <?php if(count($myBooks) > 0): ?>
        <h2>My Books (<?php echo e(count($myBooks)); ?>)</h2>
        <p>These are the books assigned to your account. They should be visible in your book list.</p>
        <table>
            <tr><th>ID</th><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Created</th></tr>
            <?php $__currentLoopData = $myBooks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $book): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($book->id); ?></td>
                    <td><?php echo e($book->title); ?></td>
                    <td><?php echo e($book->author); ?></td>
                    <td><?php echo e($book->isbn); ?></td>
                    <td><?php echo e($book->category); ?></td>
                    <td><?php echo e($book->created_at); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
    <?php endif; ?>
    
    <hr>
    <div class="info">
        <strong>📝 Note:</strong> After assigning books, refresh your <a href="/books.html">Books page</a> to see them.<br>
        New books you add will automatically be assigned to your account.
    </div>
</body>
</html>

<?php /**PATH C:\xampp\htdocs\LibraTrack\resources\views/book-assignment.blade.php ENDPATH**/ ?>
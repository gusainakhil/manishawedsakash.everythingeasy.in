<?php require dirname(__DIR__) . '/includes/bootstrap.php';
admin_required();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verify_csrf();
    $pdo->prepare('DELETE FROM guests WHERE id=?')->execute([(int) $_POST['delete']]);
    flash('success', 'Guest deleted successfully.');
    redirect('admin/guests.php');
}
$q = trim($_GET['q'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM guests WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY created_at DESC');
$like = "%$q%";
$stmt->execute([$like, $like, $like]);
$guests = $stmt->fetchAll();

function render_guest_rows(array $guests): void
{
    foreach ($guests as $g):
        $url = base_url('invite/' . $g['slug']); ?>
        <tr>
            <td><b><?= e($g['name']) ?></b><small><?= e($g['relation'] ?: $g['phone']) ?></small></td>
            <td><b><?= $g['invitation_views'] ? 'Yes' : 'No' ?></b><small><?= $g['invitation_views'] ?>
                    views<?= $g['last_viewed_at'] ? ' · ' . e(date('d M, g:i A', strtotime($g['last_viewed_at']))) : '' ?></small>
            </td>
            <td><span
                    class="status <?= e($g['rsvp_status']) ?>"><?= e(ucfirst($g['rsvp_status'])) ?></span><?php if ($g['rsvp_guests']): ?><small><?= $g['rsvp_guests'] ?>
                        guest(s)</small><?php endif; ?></td>
            <td class="actions"><a href="guest-form.php?id=<?= $g['id'] ?>">Edit</a><a target="_blank"
                    href="<?= e($url) ?>">Open</a><a target="_blank"
                    href="https://wa.me/?text=<?= rawurlencode('Hi ' . $g['name'] . ', you are warmly invited to our wedding celebration: ' . $url) ?>">Share</a>
                <form method="post" onsubmit="return confirm('Delete this guest?')"><?= csrf_field() ?><button
                        name="delete" value="<?= $g['id'] ?>">Delete</button></form>
            </td>
        </tr><?php
    endforeach;
    if (!$guests): ?>
        <tr>
            <td colspan="4" class="empty">No matching guests found.</td>
        </tr><?php
    endif;
}

if (isset($_GET['ajax'])) {
    render_guest_rows($guests);
    exit;
}

$pageTitle = 'Guests';
require '_header.php'; ?>
<section class="page-head">
    <div>
        <h1>Guest invitations</h1>
        <p>Create, personalize, share and track every invitation.</p>
    </div><a class="btn primary" href="guest-form.php">+ Add Guest</a>
</section>
<section class="panel">
    <form class="search" data-guest-search><input name="q" value="<?= e($q) ?>" placeholder="Search guest, phone or email…"><button
            class="btn">Search</button></form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Guest</th>
                    <th>Viewed</th>
                    <th>RSVP</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody data-guest-results><?php render_guest_rows($guests); ?>
            </tbody>
        </table>
    </div>
</section><?php require '_footer.php'; ?>

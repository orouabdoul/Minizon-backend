<?php
$f = __DIR__ . '/app/Http/Controllers/Admin/AdminMessagingController.php';
$c = file_get_contents($f);
$old = "            'is_voice_message' => \$attachType === 'audio' && \$m->attachment_path !== null,\n            'attachment'       => \$attachment,";
$new = "            'is_voice_message' => \$attachType === 'audio' && \$m->attachment_path !== null,\n            'is_delivered'     => \$m->delivered_at !== null,\n            'delivered_at'     => \$m->delivered_at?->toIso8601String(),\n            'attachment'       => \$attachment,";
$result = str_replace($old, $new, $c, $count);
file_put_contents($f, $result);
echo $count > 0 ? "OK: replaced $count occurrence(s)\n" : "ERROR: pattern not found\n";

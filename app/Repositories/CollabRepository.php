<?php

namespace App\Repositories;

use App\Core\Database as DB;

final class CollabRepository
{
    public function approvedDesigner(int $userId): ?array
    {
        return DB::row('select * from designers where user_id=? and status="approved"', [$userId]);
    }

    public function event(int $id): ?array
    {
        return DB::row('select c.*,d.display_name host_name from collab_events c join designers d on d.id=c.host_designer_id where c.id=?', [$id]);
    }

    public function bySlug(string $slug): ?array
    {
        return DB::row('select c.*,d.display_name host_name from collab_events c join designers d on d.id=c.host_designer_id where c.slug=?', [$slug]);
    }

    public function participant(int $collabId, int $designerId): ?array
    {
        return DB::row('select * from collab_participants where collab_id=? and designer_id=?', [$collabId,$designerId]);
    }

    public function participants(int $id): array
    {
        return DB::rows('select cp.*,d.display_name,d.store_slug,(select count(*) from collab_files f where f.participant_id=cp.id and f.file_kind="terms") terms_count from collab_participants cp join designers d on d.id=cp.designer_id where cp.collab_id=? order by cp.id', [$id]);
    }

    public function changesOpen(int $id): bool
    {
        return (bool)DB::row('select id from collab_events where id=? and snapshot_at is null and upload_deadline>now() and status="collecting"', [$id]);
    }

    public function publicForDesigner(int $designerId): array
    {
        return DB::rows('select c.* from collab_events c join collab_participants cp on cp.collab_id=c.id where cp.designer_id=? and cp.eligibility="eligible" and c.status="ready" and c.ip_risk_state in ("clear","approved") and c.snapshot_at is not null and c.final_zip_path is not null and c.sale_starts_at<=now() and c.sale_close_date>=current_date order by c.sale_starts_at desc', [$designerId]);
    }
}

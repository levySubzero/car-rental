<?php

namespace Tests\Support;

use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only owner for media collections (gadget.photos, gadget.docs). Its table is
 * created by MediaFixtures::setUp().
 *
 * @property int $id
 * @property string $name
 */
class Gadget extends Model
{
    use HasMedia;

    protected $guarded = [];
}

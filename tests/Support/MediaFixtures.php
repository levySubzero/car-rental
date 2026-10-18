<?php

namespace Tests\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Media test setup: fake disks, a test-only owner model (Gadget) with collections,
 * and real files built at runtime so MIME sniffing sees genuine bytes.
 */
final class MediaFixtures
{
    public const COLLECTIONS = [
        'gadget.photos' => [
            'label' => 'Gadget photos',
            'owner' => 'gadget',
            'visibility' => 'public',
            'max_files' => 3,
            'max_kb' => 2048,
            'mimes' => ['jpg', 'png', 'webp', 'heic'],
            'conversions' => ['thumb', 'card'],
            'upload' => ['permission' => 'users.update'],
        ],
        'gadget.docs' => [
            'label' => 'Gadget documents',
            'owner' => 'gadget',
            'visibility' => 'private',
            'max_files' => 2,
            'max_kb' => 2048,
            'mimes' => ['jpg', 'png', 'pdf'],
            'conversions' => [],
            'upload' => ['permission' => 'users.update', 'token' => true],
        ],
        'site.hero' => [
            'label' => 'Home page hero',
            'owner' => null,
            'visibility' => 'public',
            'max_files' => 1,
            'max_kb' => 2048,
            'mimes' => ['jpg', 'png', 'webp'],
            'conversions' => ['thumb'],
            'upload' => ['permission' => 'settings.home.update'],
        ],
    ];

    public static function setUp(): void
    {
        Storage::fake('media-public-local', ['url' => 'http://localhost/media-files', 'throw' => true]);
        Storage::fake('media-private-local', ['throw' => true]);

        config()->set('media.disks', ['public' => 'media-public-local', 'private' => 'media-private-local']);
        config()->set('media.collections', self::COLLECTIONS);

        Relation::morphMap(['gadget' => Gadget::class]);
        Gate::policy(Gadget::class, GadgetPolicy::class);

        Schema::create('gadgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * A real JPEG. With $orientation, an EXIF block is added holding that orientation
     * and a GPS tag, as a phone camera would write.
     */
    public static function jpeg(string $name = 'photo.jpg', int $width = 1200, int $height = 600, ?int $orientation = null): UploadedFile
    {
        $bytes = self::gdImage($width, $height, fn ($image) => imagejpeg($image, null, 90));

        if ($orientation !== null) {
            // Insert APP1 right after the SOI marker.
            $bytes = substr($bytes, 0, 2).self::exifSegment($orientation).substr($bytes, 2);
        }

        return self::file($name, $bytes);
    }

    public static function png(string $name = 'logo.png', int $width = 400, int $height = 400): UploadedFile
    {
        return self::file($name, self::gdImage($width, $height, fn ($image) => imagepng($image)));
    }

    public static function pdf(string $name = 'licence.pdf'): UploadedFile
    {
        $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";

        return self::file($name, $pdf);
    }

    /**
     * A PHP script wearing an image name.
     */
    public static function disguisedScript(string $name = 'evil.jpg'): UploadedFile
    {
        return self::file($name, "<?php system(\$_GET['cmd']); ?>\n");
    }

    /**
     * Bytes that claim to be a JPEG but cannot be decoded.
     */
    public static function brokenJpeg(string $name = 'broken.jpg'): UploadedFile
    {
        return self::file($name, "\xFF\xD8\xFF\xE0".str_repeat("\x00", 64));
    }

    public static function file(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'media-test-');
        file_put_contents($path, $bytes);

        // Test mode: skips is_uploaded_file(), keeps real finfo sniffing.
        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * Whether stored bytes still carry an EXIF block.
     */
    public static function hasExif(string $bytes): bool
    {
        return str_contains($bytes, "Exif\x00\x00");
    }

    /**
     * @param  callable(\GdImage): mixed  $encode
     */
    private static function gdImage(int $width, int $height, callable $encode): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, (int) imagecolorallocate($image, 200, 30, 30));
        imagefilledrectangle($image, intdiv($width, 2), 0, $width, $height, (int) imagecolorallocate($image, 30, 30, 200));

        ob_start();
        $encode($image);

        return (string) ob_get_clean();
    }

    private static function exifSegment(int $orientation): string
    {
        // Little-endian TIFF: IFD0 at 8 with Orientation and a GPS IFD pointer; GPS IFD at 38.
        $tiff = "II\x2A\x00".pack('V', 8)
            .pack('v', 2)
            .pack('vvVvv', 0x0112, 3, 1, $orientation, 0)
            .pack('vvVV', 0x8825, 4, 1, 38)
            .pack('V', 0)
            .pack('v', 1)
            .pack('vvV', 0x0001, 2, 2)."N\x00\x00\x00"
            .pack('V', 0);

        $payload = "Exif\x00\x00".$tiff;

        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }
}

<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * 后台上传图片的统一入口。
 *
 * 只接受真实 MIME 为图片、扩展名在白名单内且不超过 5MB 的文件；
 * 落盘文件名由服务端随机生成，扩展名按检测到的 MIME 推导，
 * 避免 "shell.php" / "a.php.jpg" 之类的文件被当成脚本执行。
 */
class ImageUpload
{
    private const MAX_SIZE = 5 * 1024 * 1024;

    /** MIME => 扩展名 */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * 保存单个文件，返回库内使用的相对路径（含 upload_image_dir 前缀）。
     *
     * @throws ValidationException
     */
    public static function store(Request $request, string $field): string
    {
        $file = $request->file($field);

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw ValidationException::withMessages([$field => '图片上传失败，请重试']);
        }

        $mime = $file->getMimeType();
        if (!isset(self::MIME_EXTENSIONS[$mime])) {
            throw ValidationException::withMessages([$field => '只允许上传 jpg/png/gif/webp 格式的图片']);
        }

        if ($file->getSize() > self::MAX_SIZE) {
            throw ValidationException::withMessages([$field => '图片不能超过 5MB']);
        }

        $dir = config('app.upload_image_dir');
        $name = $dir.date('Ymd').'_'.bin2hex(random_bytes(8)).'.'.self::MIME_EXTENSIONS[$mime];

        $file->move(public_path($dir), basename($name));

        return $name;
    }

    /**
     * 保存多文件，返回用逗号拼接的路径串（商品相册用）。
     *
     * @throws ValidationException
     */
    public static function storeMany(Request $request, string $field): string
    {
        $images = $request->file($field);
        $images = is_array($images) ? $images : [$images];

        $names = [];
        foreach ($images as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $mime = $file->getMimeType();
            if (!$file->isValid() || !isset(self::MIME_EXTENSIONS[$mime])) {
                throw ValidationException::withMessages([$field => '只允许上传 jpg/png/gif/webp 格式的图片']);
            }
            if ($file->getSize() > self::MAX_SIZE) {
                throw ValidationException::withMessages([$field => '图片不能超过 5MB']);
            }

            $dir = config('app.upload_image_dir');
            $name = $dir.date('Ymd').'_'.bin2hex(random_bytes(8)).'.'.self::MIME_EXTENSIONS[$mime];
            $file->move(public_path($dir), basename($name));
            $names[] = $name;
        }

        return implode(',', $names);
    }
}

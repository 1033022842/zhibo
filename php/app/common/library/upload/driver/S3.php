<?php

namespace app\common\library\upload\driver;

use think\file\UploadedFile;
use app\common\library\media\Media;
use app\common\library\upload\Driver;

/**
 * 上传到对象存储（S3 协议）的驱动
 *
 * 数据库里仍然记录站内逻辑路径（/storage/topic/...），
 * 真实对象 key 由 Media::keyFor() 映射成 <pub|sec>/<project>/topic/...，
 * 对外访问时再换成签名 URL，历史数据与前端无需改动。
 */
class S3 extends Driver
{
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function save(UploadedFile $file, string $saveName): bool
    {
        $path = $file->getPathname();
        if (!is_file($path)) {
            return false;
        }
        return Media::putFile($path, $saveName, (string) $file->getMime());
    }

    public function delete(string $saveName): bool
    {
        return Media::delete($saveName);
    }

    public function url(string $saveName, string|bool $domain = true, string $default = ''): string
    {
        if ($saveName === '') {
            $saveName = $default;
        }
        if ($saveName === '') {
            return '';
        }
        // 数据库只存相对路径；带域名/签名由 Media::url() 在输出时统一处理
        if (preg_match('#^https?://#i', $saveName)) {
            return $saveName;
        }
        return '/' . ltrim(str_replace('\\', '/', $saveName), '/');
    }

    public function exists(string $saveName): bool
    {
        if ($saveName === '') {
            return false;
        }
        return Media::exists($saveName);
    }
}

<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use app\common\library\media\Media;

/**
 * 本地素材 → 对象存储迁移
 *
 * 用法：
 *   php think media:migrate                 # 预演（只统计，不传）
 *   php think media:migrate --run           # 真正上传
 *   php think media:migrate --run --dir=chat_media      # 只迁某个子目录
 *   php think media:migrate --run --delete  # 上传成功后删除本地文件
 *
 * 对象 key 由 Media::keyFor() 决定（pub|sec/<project>/<相对路径>），
 * 数据库里的 /storage/xxx 路径保持不变，因此迁移后无需改库。
 */
class MediaMigrate extends Command
{
    protected function configure()
    {
        $this->setName('media:migrate')
            ->addOption('run', null, Option::VALUE_NONE, '真正执行上传（默认只预演）')
            ->addOption('dir', null, Option::VALUE_REQUIRED, '只迁移 public/storage 下的某个子目录')
            ->addOption('delete', null, Option::VALUE_NONE, '上传成功后删除本地文件')
            ->addOption('concurrency-note', null, Option::VALUE_NONE, '（占位）')
            ->setDescription('把本地 public/storage 素材迁移到对象存储');
    }

    protected function execute(Input $input, Output $output): int
    {
        if (!Media::storageEnabled()) {
            $output->error('未启用对象存储写入（media.storage_enabled=1 才可用）');
            return 1;
        }

        $root   = rtrim(app()->getRootPath() . 'public/storage', '/\\');
        if (!is_dir($root)) {
            $output->error('本地素材目录不存在：' . $root);
            return 1;
        }

        $run    = (bool) $input->getOption('run');
        $delete = (bool) $input->getOption('delete');
        $sub    = trim((string) $input->getOption('dir'), '/\\');

        $base = $sub !== '' ? $root . '/' . $sub : $root;
        if (!is_dir($base)) {
            $output->error('子目录不存在：' . $base);
            return 1;
        }

        $output->writeln(($run ? '[执行]' : '[预演]') . ' 扫描：' . $base);

        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            if ($f->isFile()) {
                $files[] = $f->getPathname();
            }
        }
        sort($files);

        $totalBytes = array_sum(array_map('filesize', $files));
        $output->writeln(sprintf('共 %d 个文件，%.2f GB', count($files), $totalBytes / 1073741824));

        $ok = 0; $fail = 0; $skipped = 0; $bytes = 0;
        $t0 = microtime(true);

        foreach ($files as $i => $path) {
            $rel = substr(str_replace('\\', '/', $path), strlen($root) + 1);
            $storagePath = '/storage/' . $rel;

            if (!$run) {
                $ok++;
                continue;
            }

            try {
                if (Media::exists($storagePath)) {
                    $skipped++;
                    if ($delete) { @unlink($path); }
                    continue;
                }
                if (!Media::putFile($path, $storagePath)) {
                    throw new \RuntimeException('PUT 返回失败');
                }
                $ok++;
                $bytes += (int) filesize($path);
                if ($delete) { @unlink($path); }
            } catch (\Throwable $e) {
                $fail++;
                $output->writeln("  ✗ {$rel} — {$e->getMessage()}");
            }

            if (($i + 1) % 100 === 0 || $i + 1 === count($files)) {
                $el = max(0.001, microtime(true) - $t0);
                $output->writeln(sprintf('  %d/%d  成功 %d  跳过 %d  失败 %d  %.1f MB/s',
                    $i + 1, count($files), $ok, $skipped, $fail, $bytes / 1048576 / $el));
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('完成：成功 %d，跳过 %d，失败 %d', $ok, $skipped, $fail));
        if (!$run) {
            $output->writeln('这是预演。加 --run 才会真正上传。');
        } elseif ($delete) {
            $output->writeln('本地文件已删除（--delete）。');
        }

        return $fail > 0 ? 2 : 0;
    }
}

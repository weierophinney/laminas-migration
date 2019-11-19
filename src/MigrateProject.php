<?php

/**
 * @see       https://github.com/laminas/laminas-migration for the canonical source repository
 * @copyright https://github.com/laminas/laminas-migration/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-migration/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Migration;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

use function file_get_contents;
use function file_put_contents;
use function rename;
use function sprintf;
use function strlen;
use function substr;

class MigrateProject
{
    /** @var Directory */
    private $dir;

    public function __construct()
    {
        $this->dir = new Directory();
    }

    public function __invoke($path, callable $filter, SymfonyStyle $io)
    {
        $io->writeln('<info>Performing migration replacements</info>');
        foreach ($this->traverseFiles($path, $filter) as $file) {
            $io->writeln(sprintf('- Examining file %s', $file->getRealPath()));
            $this->performReplacements($file->getRealPath(), $path);
        }
    }

    /**
     * @param string $path
     * @return RecursiveIteratorIterator|SplFileInfo[]
     */
    public function traverseFiles($path, callable $filter)
    {
        // Ensure paths are normalized as UNIX style paths, and that we do not
        // traverse . and ..
        $dir = new RecursiveDirectoryIterator(
            $path,
            RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::UNIX_PATHS
        );

        return new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator($dir, $filter));
    }

    /**
     * Perform replacements in $file, and rename $file if necessary
     *
     * @param string $file File being examined and updated
     * @param string $projectPath Project root path
     * @return void
     */
    public function performReplacements($file, $projectPath)
    {
        $content  = file_get_contents($file);
        $replaced = Helper::replace($content);

        if ($replaced !== $content) {
            file_put_contents($file, $replaced);
        }

        // Rename the file if necessary.
        // Only rewrite the portion under the project root path.
        $newName = sprintf('%s/%s', $projectPath, Helper::replace(substr($file, strlen($projectPath) + 1)));
        if ($newName !== $file) {
            $this->dir->createParentDirectory($newName);
            rename($file, $newName);
        }
    }
}
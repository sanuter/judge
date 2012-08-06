<?php
/**
 * Generate tag file for use with MageCompatibility plugin for Judge
 */

if (2 != count($argv)) {
    die('Please submit exactly one param: The path to the Magento app directory' . PHP_EOL);
}
$pathToMagentoBaseDir = $argv[1];
if (substr($pathToMagentoBaseDir, -1) != '/') {
    $pathToMagentoBaseDir .= '/';
}
if (false === file_exists($pathToMagentoBaseDir . '/app/Mage.php')) {
    die('Are you sure, there is a Magento? Couldn\'t find Mage.php!' . PHP_EOL);
}

$tagger = new Tagger($pathToMagentoBaseDir);
$tagger->run();

class Tagger
{
    protected $tagDir;
    protected $pathToMagentoBaseDir;
    protected $edition;
    protected $version;

    public function __construct($pathToMagentoBaseDir)
    {
        $this->tagDir = dirname(__FILE__) . '/../var/tags/';
        $this->pathToMagentoBaseDir = $pathToMagentoBaseDir;
        $this->verifyMagento($pathToMagentoBaseDir);
    }

    protected function verifyMagento($pathToMagentoBaseDir)
    {
        include $pathToMagentoBaseDir . 'app/Mage.php';

        $this->edition = (method_exists('Mage', 'getEdition')) ? Mage::getEdition() : 'Community';
        $this->version = Mage::getVersion();
        echo 'Analyzing Magento ' . $this->version . ' (' . $this->edition . ' Edition)...' . PHP_EOL;
    }

    protected function getTagFileName()
    {
        return strtolower($this->edition) . '-' . $this->version . '.tags';
    }

    protected function getRawTagFilePath()
    {
        return $this->tagDir . $this->getTagFileName() . '.raw';
    }

    public function run()
    {
        $command = sprintf(
            'cd %s && ctags -R --languages=php --totals=yes --tag-relative=yes --PHP-kinds=+cidf-v -h ".ph" --fields=+n -f tags .',
            $this->pathToMagentoBaseDir,
            $this->pathToMagentoBaseDir,
            $this->edition,
            $this->version
        );

        exec($command, $output);

        rename($this->pathToMagentoBaseDir . 'tags', $this->getRawTagFilePath());

        $rawTagFile = fopen($this->getRawTagFilePath(), 'r');
        $tagFile = fopen($this->tagDir . $this->getTagFileName(), 'w');
        $tagFileLineNumber = 0;
        while ($line = fgets($rawTagFile)) {
            ++$tagFileLineNumber;
            if (0 == strlen(trim($line))) {
                // skip empty lines
                continue;
            }
            if ('!_T' == substr($line, 0,3)) {
                // skip comment lines
                continue;
            }
            $line = preg_replace('/\/\^(\W*)(\w)/', '/^$2', $line);
            list($tag, $path, $codeLine, $type, $sourceLineNumber) = explode("\t", $line);

            if ('j' == $type) {
                // skip js
                continue;
            }

            $strippedCodeLine = preg_replace('/".*"/', '', $codeLine);
            $strippedCodeLine = preg_replace('/\'.*\'/', '', $strippedCodeLine);
            if (substr_count($strippedCodeLine, '(') !== substr_count($strippedCodeLine, ')')) {
                $codeLine = $this->getCompleteFunctionDefinition($path, $tag, $sourceLineNumber);
            }

            fputs($tagFile, trim(implode(
                "\t",
                array($tag, $path, $codeLine, $type, $sourceLineNumber)
            )) . "\n");
        }
        fclose($rawTagFile);
        unlink($this->getRawTagFilePath());
    }

    protected function getCompleteFunctionDefinition($path, $tag, $sourceLineNumber)
    {
        $sourceLineNumber = (int) trim(str_replace('line:', '', $sourceLineNumber));
        $sourceFile = fopen($this->pathToMagentoBaseDir . $path, 'r');
        $currentLineNumber = 0;
        while(!feof($sourceFile))
        {
            ++$currentLineNumber;
            $line = fgets($sourceFile);
            if ($sourceLineNumber == $currentLineNumber) {
                $functionDefinition = '/^' . str_replace("\n", '', $line);
            }
            if ($sourceLineNumber < $currentLineNumber) {
                $bodyStartPos = strpos($line, '{');
                if (false !== $bodyStartPos) {
                    $line = substr($line, 0, $bodyStartPos);
                }
                $functionDefinition .= ' ' . trim($line);
                if (substr_count($line, '(') < substr_count($line, ')')) {
                    $functionDefinition = trim($functionDefinition) . '$/;"';
                    break;
                }
            }
        }
        fclose($sourceFile);

        return $functionDefinition;
    }
}
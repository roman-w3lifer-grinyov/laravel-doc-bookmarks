<?php

namespace w3lifer\laravel;

use w3lifer\netscapeBookmarks\NetscapeBookmarks;

/**
 * @see https://github.com/laravel/docs
 */
class DocBookmarks
{
    const BASE_LINK_GITHUB_COM =
        'https://raw.githubusercontent.com/laravel/docs';

    const BASE_LINK_LARAVEL_COM = 'https://laravel.com/docs';

    const REGEXP_SECTION_NAMES = '=\- ## (.+?)\n=';

    const FOUR_SPACES = '    ';

    /**
     * @var array Default options.
     */
    private $options = [
        'version' => 'master',
    ];

    /**
     * @var string
     */
    private $baseUrlToGitHubCom = '';

    /**
     * @var string
     */
    private $baseUrlToLaravelCom = '';

    /**
     * @var array
     */
    private $tableOfContents = [];

    /**
     * @var array
     */
    private $bookmarks = [];

    /**
     * @param array $options
     */
    public function __construct($options = [])
    {
        $this->options = array_merge($this->options, $options);
        $this->baseUrlToGitHubCom =
            self::BASE_LINK_GITHUB_COM . '/' . $this->options['version'];
        $this->baseUrlToLaravelCom =
            self::BASE_LINK_LARAVEL_COM . '/' . $this->options['version'];
    }

    /**
     * @return string
     */
    public function getAsNetscapeBookmarks()
    {
        if (!$this->tableOfContents) {
            $this->tableOfContents = $this->getTableOfContents();
        }
        if (!$this->bookmarks) {
            $this->makeBookmarks($this->tableOfContents);
        }
        return new NetscapeBookmarks($this->bookmarks);
    }

    /**
     * @return array
     */
    public function getAsArray()
    {
        if (!$this->tableOfContents) {
            $this->tableOfContents = $this->getTableOfContents();
        }
        if (!$this->bookmarks) {
            $this->makeBookmarks($this->tableOfContents);
        }
        return $this->bookmarks;
    }

    /**
     * Example of the returned array:
     * ``` php
     * // ...
     * 'Getting Started' => [
     *     'Installation' => '/docs/master/installation',
     *     'Configuration' => '/docs/master/configuration',
     *     'Directory Structure' => '/docs/master/structure',
     *     'Homestead' => '/docs/master/homestead',
     *     'Valet' => '/docs/master/valet',
     *     'Deployment' => '/docs/master/deployment',
     * ],
     * // ...
     * ```
     * @return array
     * @see https://raw.githubusercontent.com/laravel/docs/master/documentation.md
     */
    private function getTableOfContents()
    {
        $tableOfContents =
            file_get_contents(
                $this->baseUrlToGitHubCom . '/' . 'documentation.md'
            );

        // Section names

        preg_match_all(self::REGEXP_SECTION_NAMES, $tableOfContents, $matches);
        $sectionNames = $matches[1];

        // Section contents

        $sectionContents =
            preg_split(self::REGEXP_SECTION_NAMES, $tableOfContents);
        array_shift($sectionContents);

        // Table of contents

        $tableOfContents = [];

        foreach ($sectionNames as $index => $sectionName) {
            preg_match_all(
                '=\s*\[(.+?)\]\((.+?)\)=',
                $sectionContents[$index],
                $matches
            );
            $articleNames = $matches[1];
            $articleFilenames = $matches[2];
            foreach ($articleFilenames as $index => $pathToArticle) {
                $partsOfPathToArticle = explode('/', $pathToArticle);
                $articleFilename = array_pop($partsOfPathToArticle);
                if ($articleFilename === '{{version}}') {
                    continue; // API Documentation
                }
                $tableOfContents[$sectionName][$articleNames[$index]] =
                    $articleFilename;
            }
        }

        return $tableOfContents;
    }

    private function makeBookmarks($tableOfContents)
    {
        $this->bookmarks['Documentation'] = $this->baseUrlToLaravelCom;
        foreach ($tableOfContents as $sectionName => $articles) {
            $this->bookmarks[$sectionName] = [];
            foreach ($articles as $articleName => $articleFilename) {
                try {
                    $articleContent =
                        file_get_contents(
                            $this->baseUrlToGitHubCom . '/' .
                            $articleFilename . '.md'
                        );
                } catch (Exception $e) {
                }
                $this->processAnchors(
                    $sectionName,
                    $articleName,
                    $articleFilename,
                    $articleContent
                );
            }
        }
        $this->addSerialNumbersToSectionAndArticleNames();
    }

    /**
     * @param string $sectionName
     * @param string $articleName
     * @param string $articleFilename
     * @param string $articleContent
     * @see https://raw.githubusercontent.com/laravel/docs/master/installation.md
     */
    private function processAnchors(
        $sectionName,
        $articleName,
        $articleFilename,
        $articleContent
    ) {
        $this->processH1Anchor(
            $sectionName,
            $articleName,
            $articleFilename,
            $articleContent
        );
        preg_match_all(
            '=\n(#{2,6} .+?)\n\n=',
            $articleContent,
            $headers
        );
        foreach ($headers[1] as $index => $header) {
            $headerWithSerialNumber =
                self::getHeaderNameWithSerialNumber(
                    $sectionName,
                    $articleName,
                    $header
                );
            $this->bookmarks
                [$sectionName]
                    [$articleName]
                        [$headerWithSerialNumber] =
                            $this->baseUrlToLaravelCom . '/' .
                                $articleFilename .
                                    self::getAnchor($header);
        }
    }

    /**
     * @param string $sectionName
     * @param string $articleName
     * @param string $articleFilename
     * @param string $articleContent
     */
    private function processH1Anchor(
        $sectionName,
        $articleName,
        $articleFilename,
        $articleContent
    ) {
        preg_match_all('=^\s*# (.+?)\n\n=', $articleContent, $matches);
        $this->bookmarks[$sectionName][$articleName] = [
            '0. ' . $matches[1][0] =>
                $this->baseUrlToLaravelCom . '/' . $articleFilename,
        ];
    }

    /**
     * ```
     * [
     *   <section name> => [
     *     <article name> => [
     *       <header name> => [
     *          <some number of hash signs> => <counter of the same headers>,
     *       ],
     *     ],
     *   ],
     * ]
     * ```
     * @var array
     */
    private static $serialNumberStorage = [];

    /**
     * @var string
     */
    private static $lastInsertedHashSigns = '';

    /**
     * @param string $sectionName
     * @param string $articleName
     * @param string $headerNamePrefixedWithHashSigns
     * @return string
     */
    private static function getHeaderNameWithSerialNumber(
        $sectionName,
        $articleName,
        $headerNamePrefixedWithHashSigns
    ) {
        // Initialize storage for serial numbers

        if (!isset(self::$serialNumberStorage[$sectionName][$articleName])) {
            self::$serialNumberStorage[$sectionName][$articleName] = [];
        }

        // Initialize counter for every number of hash signs

        preg_match('=^#+=', $headerNamePrefixedWithHashSigns, $matches);
        $hashSigns = $matches[0];

        if (!isset(
            self::$serialNumberStorage[$sectionName][$articleName][$hashSigns]
        )) {
            self::$serialNumberStorage
                [$sectionName][$articleName][$hashSigns] = 0;
        }

        // Reset counter for the current number of hash signs

        if (strlen($hashSigns) < strlen(self::$lastInsertedHashSigns)) {
            self::$serialNumberStorage
                [$sectionName]
                    [$articleName]
                        [self::$lastInsertedHashSigns] = 0;
        }

        // Increase counter for the current number of hash signs

        self::$serialNumberStorage[$sectionName][$articleName][$hashSigns]++;

        $serialNumberOfAnchor = substr_count($hashSigns, '#');

        $serialNumber = '';

        // Compose the serial number

        // `$i = 2` because of header H2 (##) is a control point
        for ($i = 2; $i <= $serialNumberOfAnchor; $i++) {
            $growingHashSigns = str_repeat('#', $i);
            // For the case when exists leap between headers
            // For example, #### going after ##
            if (!isset(
                self::$serialNumberStorage
                    [$sectionName][$articleName][$growingHashSigns]
            )) {
                self::$serialNumberStorage
                    [$sectionName][$articleName][$growingHashSigns] = 1;
            }
            $serialNumber .=
                self::$serialNumberStorage
                    [$sectionName][$articleName][$growingHashSigns] . '.';
        }

        // Required to reset counter (see above)

        self::$lastInsertedHashSigns = $hashSigns;

        // Compose the final header name

        $headerName =
            $serialNumber . ' ' .
                preg_replace('=^#+ =', '', $headerNamePrefixedWithHashSigns);

        return $headerName;
    }

    private static function getAnchor($anchor)
    {
        $anchor = strtolower($anchor);
        $anchor = preg_replace('=[^ \-0-9a-z]=', '', $anchor);
        $anchor = trim($anchor);
        $anchor = str_replace([' '], '-', $anchor);
        return '#' . $anchor;
    }

    private function addSerialNumbersToSectionAndArticleNames()
    {
        $i = 0;
        $bookmarks = [];
        foreach ($this->bookmarks as $sectionName => $articles) {
            if (is_array($articles)) {
                $articlesWithSerialNumbers = [];
                $j = 1;
                foreach ($articles as $articleName => $anchors) {
                    $articlesWithSerialNumbers[$j . '. ' . $articleName] =
                        $anchors;
                    $j++;
                }
                $bookmarks[$i . '. ' . $sectionName] =
                    $articlesWithSerialNumbers;
            } else {
                $bookmarks[$i . '. ' . $sectionName] = $articles;
            }
            $i++;
        }
        $this->bookmarks = $bookmarks;
    }
}

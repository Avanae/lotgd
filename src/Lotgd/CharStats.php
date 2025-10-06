<?php

declare(strict_types=1);

namespace Lotgd;

use Lotgd\Translator;
use Lotgd\Template;
use Lotgd\Output;

class CharStats
{
    private const DEFERRED_SECTIONS = ['Skills'];

    private array $stats = [];

    /**
     * Reset all stored stats.
     */
    public function clear(): void
    {
        $this->stats = [];
    }

    /**
     * Add a single stat entry.
     *
     * @param string     $section Section name
     * @param string     $label   Stat label
     * @param mixed|null $value   Value to display
     */
    public function addStat(string $section, string $label, mixed $value = null): void
    {
        if (!isset($this->stats[$section])) {
            $this->stats[$section] = [];
        }
        if ($label !== '') {
            $this->stats[$section][$label] = $value;
        }
    }

    /**
     * Replace or create a stat entry.
     */
    public function setStat(string $section, string $label, mixed $value): void
    {
        $this->addStat($section, $label, $value);
    }

    /**
     * Retrieve a previously set stat value.
     */
    public function getStat(string $section, string $label): mixed
    {
        return $this->stats[$section][$label] ?? null;
    }

    /**
     * Render the stat table to HTML.
     */
    public function render(string $buffs): string
    {
        $output = Output::getInstance();
        $charstat = Template::templateReplace('statstart');
        $deferred = [];

        foreach ($this->stats as $label => $section) {
            if (in_array($label, self::DEFERRED_SECTIONS, true)) {
                $deferred[$label] = $section;
                continue;
            }

            $charstat .= $this->renderSection($label, $section);
        }

        $charstat .= Template::templateReplace('statbuff', ['title' => Translator::translateInline('`0Buffs'), 'value' => $buffs]);

        foreach ($deferred as $label => $section) {
            $charstat .= $this->renderSection($label, $section);
        }

        $charstat .= Template::templateReplace('statend');

        return $output->appoencode($charstat, true);
    }

    private function renderSection(string $label, array $section): string
    {
        if (count($section) === 0) {
            return '';
        }

        $sectionHead = Template::templateReplace('stathead', ['title' => Translator::translateInline($label)]);
        $buffer = '';

        foreach ($section as $name => $val) {
            if ($name == $label) {
                $args = ['title' => Translator::translateInline("`0$name"), 'value' => "`^$val`0"];
                $buffer .= Template::templateReplace('statbuff', $args);
            } else {
                $args = ['title' => Translator::translateInline("`&$name`0"), 'value' => "`^$val`0"];
                $buffer .= $sectionHead . Template::templateReplace('statrow', $args);
                $sectionHead = '';
            }
        }

        return $buffer;
    }
}

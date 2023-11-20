<?php

namespace League\Plates\Template;

use Exception;
use League\Plates\Engine;
use League\Plates\Exception\TemplateNotFound;
use LogicException;
use Throwable;

/**
 * Container which holds template data and provides access to template functions.
 */
class Template
{
    const SECTION_MODE_REWRITE = 1;
    const SECTION_MODE_PREPEND = 2;
    const SECTION_MODE_APPEND = 3;

    /**
     * Set section content mode: rewrite/append/prepend
     * @var int
     */
    protected $sectionMode = self::SECTION_MODE_REWRITE;

    /**
     * @var array<string, int> where string is section name and int sectionMode
     */
    protected $sectionsMode = [];

    /**
     * Instance of the template engine.
     * @var Engine
     */
    protected $engine;

    /**
     * The name of the template.
     * @var Name
     */
    protected $name;

    /**
     * The data assigned to the template.
     * @var array
     */
    protected $data = array();

    /**
     * An array of section content.
     * @var array
     */
    protected $sections = array();

    /**
     * The name of the section currently being rendered.
     * @var string
     */
    protected $sectionName;

    /**
     * Whether the section should be appended or not.
     * @deprecated stayed for backward compatibility, use $sectionMode instead
     * @var boolean
     */
    protected $appendSection;

    /**
     * The name of the template layout.
     * @var string|TemplateClassInterface
     */
    protected $layoutName;

    /**
     * The data assigned to the template layout.
     * @var array
     */
    protected $layoutData;

    /**
     * Create new Template instance.
     * @param Engine $engine
     * @param string $name
     */
    public function __construct(Engine $engine, $name)
    {
        $this->engine = $engine;
        $this->name = new Name($engine, $name);

        $this->data($this->engine->getData($name));
    }

    /**
     * Magic method used to call extension functions.
     * @param  string $name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->engine->getFunction($name)->call($this, $arguments);
    }

    /**
     * Alias for render() method.
     * @throws \Throwable
     * @throws \Exception
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Assign or get template data.
     * @param  array $data
     * @return array
     */
    public function data(array $data = null)
    {
        if (is_null($data)) {
            return $this->data;
        }

        return $this->data = array_merge($this->data, $data);
    }

    /**
     * Check if the template exists.
     * @return boolean
     */
    public function exists()
    {
        try {
            ($this->engine->getResolveTemplatePath())($this->name);
            return true;
        } catch (TemplateNotFound $e) {
            return false;
        }
    }

    /**
     * Get the template path.
     * @return string
     */
    public function path()
    {
        try {
            return ($this->engine->getResolveTemplatePath())($this->name);
        } catch (TemplateNotFound $e) {
            return $e->paths()[0];
        }
    }

    /**
     * Render the template and layout.
     * @param  array  $data
     * @throws \Throwable
     * @throws \Exception
     * @return string
     */
    public function render(array $data = array())
    {
        $this->data($data);

        try {
            $level = ob_get_level();
            ob_start();
            $this->display();
            $content = ob_get_clean();

            if (isset($this->layoutName)) {
                $layout = $this->engine->make($this->layoutName);
                $layout->sections = array_merge($this->sections, array('content' => $content));
                $layout->sectionsMode = array_merge($layout->sectionsMode, $this->sectionsMode);
                $content = $layout->render($this->layoutData);
            }

            return $content;
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }
    }


    protected function display() {
        $path = ($this->engine->getResolveTemplatePath())($this->name);

        (function() {
            extract($this->data);
            include func_get_arg(0);
        })($path);
    }

    /**
     * Set the template's layout.
     * @param  string|TemplateClassInterface $name
     * @param  array  $data
     * @return null
     */
    public function layout($name, array $data = array())
    {
        $this->layoutName = $name;
        $this->layoutData = array_merge($this->data, $data);
    }


    private function mustStopRenderingSection(): bool
    {
        if (isset($this->sections[$this->sectionName]) && $this->sectionMode == self::SECTION_MODE_REWRITE)
            return true;

        return false;
    }

    /**
     * Start a new section block.
     * @param  string  $name
     * @return bool
     */
    public function start($name)
    {
        if ($name === 'content') {
            throw new LogicException(
                'The section name "content" is reserved.'
            );
        }

        if ($this->sectionName) {
            throw new LogicException('You cannot nest sections within other sections.');
        }

        $this->sectionName = $name;

        if ($this->mustStopRenderingSection())
            return false;

        return ob_start();
    }

    /**
     * Start a new section block in APPEND mode.
     * @param  string $name
     * @return bool
     */
    public function push($name)
    {
        $this->appendSection = true; /* for backward compatibility */
        $this->sectionMode = $this->sectionsMode[$name] = self::SECTION_MODE_APPEND;
        $this->start($name);
        return true;
    }

    /**
     * Start a new section block in PREPEND mode.
     * @param  string $name
     * @return bool
     */
    public function unshift($name)
    {
        $this->appendSection = false; /* for backward compatibility */
        $this->sectionMode = $this->sectionsMode[$name] = self::SECTION_MODE_PREPEND;
        $this->start($name);
        return true;
    }

    /**
     * Stop the current section block.
     * @return null
     */
    public function stop()
    {
        if (is_null($this->sectionName)) {
            throw new LogicException(
                'You must start a section before you can stop it.'
            );
        }


        if (! $this->mustStopRenderingSection()) {

            if (!isset($this->sections[$this->sectionName])) {
                $this->sections[$this->sectionName] = '';
            }

            switch ($this->sectionMode) {

                case self::SECTION_MODE_REWRITE:
                    $this->sections[$this->sectionName] = ob_get_clean();
                    break;

                case self::SECTION_MODE_APPEND:
                    $this->sections[$this->sectionName] .= ob_get_clean();
                    break;

                case self::SECTION_MODE_PREPEND:
                    $this->sections[$this->sectionName] = ob_get_clean().$this->sections[$this->sectionName];
                    break;

            }
        }
        $this->sectionName = null;
        $this->sectionMode = self::SECTION_MODE_REWRITE;
        $this->appendSection = false; /* for backward compatibility */
    }

    /**
     * Alias of stop().
     * @return null
     */
    public function end()
    {
        $this->stop();
    }

    /**
     * Returns the content for a section block.
     * @param  string      $name    Section name
     * @param  string      $default Default section content
     * @return string|null
     */
    public function section($name, $default = null)
    {
        if (!isset($this->sections[$name])) {
            return $default;
        }

        return $this->sections[$name];
    }

    private function getSectionMode($name): int
    {
        return $this->sectionsMode[$name] ?? self::SECTION_MODE_REWRITE;
    }

    /**
     * Echo the content for a section block else return bool.
     *
     * Usage :
     * <?php if ($this->startSection('exampleSection')) { ?>
     *  Default Content
     * <?php } ?>
     * Alternative To : <?= $this->section('exampleSection', 'Default Content') ?>
     * + Feature : works with push and unshift
     * + could be used with defaultValue inline too : <?(=|php) $this->startSection('exampleSection', 'Default Content') ?>
     *
     * @param  string      $name    Section name
     * @param  string      $default Default section content
     * @return bool|string string if default is setted
     */
    public function startSection($name, $default = null)
    {
        if (isset($this->sections[$name])) {
            if ($this->getSectionMode($name) === self::SECTION_MODE_REWRITE) {
                echo $this->sections[$name];

                return  $default !== null ? '' : false;
            }

            if ($this->getSectionMode($name) === self::SECTION_MODE_PREPEND) {
                echo $this->sections[$name];

                if ($default !== null) {
                    echo $default;
                }

                return  $default !== null ? '' : true;
            }
            if ($this->getSectionMode($name) === self::SECTION_MODE_APPEND) {
                $this->sectionMode = self::SECTION_MODE_PREPEND;
                $this->start($name);


                if ($default !== null) {
                    echo $default;
                    $this->stopSection();
                }

                return $default !== null ? '' : true;
            }
        }

        return  $default !== null ? '' : true;
    }

    /**
     * Undocumented function
     *
     * @return ,null
     */
    public function stopSection() {
        if ($this->sectionName === null) {
            return;
        }

        $name = $this->sectionName;
        $this->stop();
        echo $this->sections[$name];
    }

    /**
     * Fetch a rendered template.
     * @param  string|TemplateClassInterface $name
     * @param  array  $data
     * @return string
     */
    public function fetch($name, array $data = array(), bool $useTemplateData = true)
    {
        return $this->engine->render($name, $useTemplateData ? array_merge($this->data, $data) : $data);
    }

    /**
     * Output a rendered template.
     * @param  string|TemplateClassInterface $name
     * @param  array  $data
     * @return null
     */
    public function insert($name, array $data = array(), bool $useTemplateData = false)
    {
        echo $this->fetch($name, $data, $useTemplateData);
    }

    /**
     * Apply multiple functions to variable.
     * @param  mixed  $var
     * @param  string $functions
     * @return mixed
     */
    public function batch($var, $functions)
    {
        foreach (explode('|', $functions) as $function) {
            if ($this->engine->doesFunctionExist($function)) {
                $var = call_user_func(array($this, $function), $var);
            } elseif (is_callable($function)) {
                $var = call_user_func($function, $var);
            } else {
                throw new LogicException(
                    'The batch function could not find the "' . $function . '" function.'
                );
            }
        }

        return $var;
    }

    /**
     * Escape string.
     * @param  string      $string
     * @param  null|string $functions
     * @return string
     */
    public function escape($string, $functions = null)
    {
        static $flags;

        if (!isset($flags)) {
            $flags = ENT_QUOTES | (defined('ENT_SUBSTITUTE') ? ENT_SUBSTITUTE : 0);
        }

        if ($functions) {
            $string = $this->batch($string, $functions);
        }

        return htmlspecialchars($string ?? '', $flags, 'UTF-8');
    }

    /**
     * Alias to escape function.
     * @param  string      $string
     * @param  null|string $functions
     * @return string
     */
    public function e($string, $functions = null)
    {
        return $this->escape($string, $functions);
    }

}

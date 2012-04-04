<?php

require_once 'lib/PHPTAL.php';

class BPHPTAL extends BClass
{
    protected static $_singletons = array();

    protected static $_outputMode = PHPTAL::HTML5;

    protected static $_defaultFileExt = '.zpt.html';

    protected static $_phpCodeDest;

    protected static $_forceReparse;

    protected static $_fcomVars;

    public static function bootstrap()
    {
        $config = BConfig::i();

        static::$_phpCodeDest = $config->get('storage_dir').'/compiled';
        BUtil::ensureDir(static::$_phpCodeDest);

        static::$_forceReparse = $config->get('modules/BPHPTAL/force_reparse');

        static::$_fcomVars = BData::i(true, array(
            'request' => BRequest::i(),
            'layout' => BLayout::i(),
        ));

        BPubSub::i()->on('BLayout::theme.load.before', 'BPHPTAL::onLayoutThemeLoadBefore');
    }

    public static function singleton($class)
    {
        if (empty(static::$_singletons[$class])) {
            static::$_singletons[$class] = new $class;
        }
        return static::$_singletons[$class];
    }

    public static function factory($tpl=null)
    {
        $tal = new PHPTAL($tpl);
        $tal->setPhpCodeDestination(static::$_phpCodeDest);
        $tal->setOutputMode(static::$_outputMode);

        $tal->addPreFilter(static::singleton('BPHPTAL_PreFilter'));
        $tal->setPostFilter(static::singleton('BPHPTAL_PostFilter'));
        #$tal->setTranslator(static::singleton('BPHPTAL_TranslationService'));
        $tal->setTranslator(new BPHPTAL_TranslationService);

        if (static::$_forceReparse) {
            $tal->setForceReparse(true);
        }

        $tal->set('FCOM', static::$_fcomVars);

        BPubSub::i()->fire(__METHOD__, array('tal'=>$tal, 'tpl'=>$tpl));
        return $tal;
    }

    public static function renderer($view)
    {
        $source = $view->param('source');
        if (!$source) {
            $template = $view->getTemplateFileName(static::$_defaultFileExt);
            $tal = static::factory($template);
        } else {
            $tal = static::factory();
        }
        foreach ($view->getAllArgs() as $k=>$v) {
            if ($k[0]!=='_') {
                $tal->set($k, $v);
            }
        }
        if ($source) {
            $source = '<tal:block>'.$source.'</tal:block>';
            $sourceName = $view->param('source_name');
            $tal->setSource($source, $sourceName ? $sourceName : __METHOD__);
        }
        return $tal->execute();
    }

    public static function onLayoutThemeLoadBefore($args)
    {
        $root = BLayout::i()->view('root');
        if ($root) {
            $root->xmlns('tal', 'http://xml.zope.org/namespaces/tal')
                ->xmlns('metal', 'http://xml.zope.org/namespaces/metal')
                ->xmlns('i18n', 'http://xml.zope.org/namespaces/i18n')
                ->xmlns('phptal', 'http://phptal.org/ns/phptal')
            ;
        }
    }
}

class BPHPTAL_PreFilter extends PHPTAL_PreFilter
{
    public function filter($source)
    {
        BPubSub::i()->fire(__METHOD__, array('source'=>&$source));
        return $source;
    }

    public function filterDOM(PHPTAL_Dom_Element $element)
    {
        BPubSub::i()->fire(__METHOD__, array('element'=>$element));
    }
}

class BPHPTAL_PostFilter implements PHPTAL_Filter
{
    public function filter($html)
    {
        BPubSub::i()->fire(__METHOD__, array('html'=>&$html));
        return $html;
    }
}

class BPHPTAL_TranslationService implements PHPTAL_TranslationService
{
    protected $_currentLang = 'en_US';

    protected $_currentDomain;
    protected $_domains = array();

    private $_context = array();

    public function setLanguage()
    {
        $langs = func_get_args();
        foreach($langs as $lang){
            // if $lang known use it and stop the loop
            $this->_currentLang = $lang;
            break;
        }
        return $this->_currentLang;
    }

    public function useDomain($domain)
    {
        $this->_currentDomain = $domain;
    }

    public function setVar($key, $value)
    {
        $this->_context[$key] = $value;
    }

    public function translate($key, $htmlescape=true)
    {
        $result = BLocale::_($key, $this->_context, $this->_currentDomain);
        if ($htmlescape) {
            $result = htmlspecialchars($result);
        }
        return $result;
    }

    public function setEncoding($encoding)
    {

    }
}
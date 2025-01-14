<?php

namespace Bazinga\Bundle\JsTranslationBundle\Tests\Controller;

use Bazinga\Bundle\JsTranslationBundle\Tests\WebTestCase;
use Symfony\Component\HttpKernel\Kernel;

class ControllerTest extends WebTestCase
{
    public function testGetTranslations()
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/messages.json');
        $response = $client->getResponse();

        $this->assertEquals(<<<JSON
{
    "fallback": "en",
    "defaultDomain": "messages",
    "translations": {"en":{"messages":{"hello":"hello"}}}
}

JSON
        , $response->getContent());
    }

    public function testGetTranslationsWithMultipleLocales()
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/messages.json?locales=en,fr');
        $response = $client->getResponse();

        $this->assertEquals(<<<JSON
{
    "fallback": "en",
    "defaultDomain": "messages",
    "translations": {"en":{"messages":{"hello":"hello"}},"fr":{"messages+intl-icu":{"hello_name":"bonjour {name} !"},"messages":{"hello":"bonjour"}}}
}

JSON
        , $response->getContent());
    }

    public function testGetTranslationsWithUnknownDomain()
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/unknown.json');
        $response = $client->getResponse();

        $this->assertEquals(<<<JSON
{
    "fallback": "en",
    "defaultDomain": "messages",
    "translations": {"en":[]}
}

JSON
        , $response->getContent());
    }

    public function testGetTranslationsWithUnknownLocale()
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/foo.json?locales=pt');
        $response = $client->getResponse();

        $this->assertEquals(<<<JSON
{
    "fallback": "en",
    "defaultDomain": "messages",
    "translations": {"pt":[]}
}

JSON
        , $response->getContent());
    }

    public function testGetJsTranslations()
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/messages.js');
        $response = $client->getResponse();

        $this->assertEquals(<<<JS
(function (t) {
t.fallback = 'en';
t.defaultDomain = 'messages';
// en
t.add("hello", "hello", "messages", "en");
})(Translator);

JS
        , $response->getContent());
    }

    public function testGetJsTranslationsWithMultipleLocales()
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/messages.js?locales=en,fr');
        $response = $client->getResponse();

        $this->assertEquals(<<<JS
(function (t) {
t.fallback = 'en';
t.defaultDomain = 'messages';
// en
t.add("hello", "hello", "messages", "en");
// fr
t.add("hello_name", "bonjour {name} !", "messages\u002Bintl\u002Dicu", "fr");
t.add("hello", "bonjour", "messages", "fr");
})(Translator);

JS
        , $response->getContent());
    }

    public function testGetJsTranslationsWithUnknownDomain()
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/unknown.js');
        $response = $client->getResponse();

        $this->assertEquals(<<<JS
(function (t) {
t.fallback = 'en';
t.defaultDomain = 'messages';
// en
})(Translator);

JS
        , $response->getContent());
    }

    public function testGetJsTranslationsWithUnknownLocale()
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/foo.js?locales=pt');
        $response = $client->getResponse();

        $this->assertEquals(<<<JS
(function (t) {
t.fallback = 'en';
t.defaultDomain = 'messages';
// pt
})(Translator);

JS
        , $response->getContent());
    }

    public function testGetTranslationsWithNumericKeys()
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/numerics.json?locales=en');
        $response = $client->getResponse();

        $this->assertEquals(<<<JSON
{
    "fallback": "en",
    "defaultDomain": "messages",
    "translations": {"en":{"numerics":{"7":"Nos occasions","8":"Nous contacter","12":"pr\u00e9nom","13":"nom","14":"adresse","15":"code postal"}}}
}

JSON
        , $response->getContent());
    }

    public function testGetTranslationsWithPathTraversalAttack()
    {
        $client  = static::createClient();

        // 1. `evil.js` is not accessible
        $client->request('GET', '/translations?locales=en-randomstring/../../evil');
        $response = $client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());

        // 2. let's create a random directory with a random js file
        // Fixing this issue = not creating any file here
        $client->request('GET', '/translations?locales=en-randomstring/something');

        $this->assertFileNotExists(sprintf('%s/%s/messages.en-randomstring/something.js',
            $client->getKernel()->getCacheDir(),
            'bazinga-js-translation'
        ));

        // 3. path traversal attack
        // Fixing this issue = 404
        $client->request('GET', '/translations?locales=en-randomstring/../../evil');
        $response = $client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetTranslationsWithLocaleInjection()
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/messages.json?locales=foo%0Auncommented%20code;');
        $response = $client->getResponse();

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @dataProvider getLocales
     */
    public function testGetTranslationsForDifferentLocales($locale)
    {
        $client  = static::createClient();

        $client->request('GET', '/translations/messages.json?locales=' . $locale);
        $response = $client->getResponse();

        $this->assertEquals(<<<JSON
{
    "fallback": "en",
    "defaultDomain": "messages",
    "translations": {"$locale":[]}
}

JSON
        , $response->getContent());
    }

    public function getLocales()
    {
        return array(
            'simple with two characters' => array('eo'),
            'simple with three characters' => array('zhc'),
            'lowercase underscore' => array('en_en'),
            'lowercase dashed' => array('en-en'),
            'underscored' => array('fr-FR'),
            'dashed' => array('fr_FR'),
            'with numeric value in locale' => array('es_419'),
            'locale with three parts separeted by undescore' => array('ha_Latn_NE'),
            'locale with three parts separeted by undescore with long last part' => array('ja_J2P_TRADITIONAL')
        );
    }

    public function testGetTranslationsOutsideResourcesFolder()
    {
        if (Kernel::VERSION_ID < 20800) {
            $this->markTestSkipped('This Symfony version do not support framework.paths configuration');
        }

        $client  = static::createClient();

        $crawler  = $client->request('GET', '/translations/outsider.json');
        $response = $client->getResponse();

        $this->assertEquals(<<<JSON
{
    "fallback": "en",
    "defaultDomain": "messages",
    "translations": {"en":{"outsider":{"frontpage.hi":"hi"}}}
}

JSON
            , $response->getContent());
    }

    /**
     * Test the bar domain from another bundle using a compiler pass to add into the definition using a method call.
     */
    public function testGetTranslationsFromCompilerPassAnotherBundle()
    {
        $client  = static::createClient();

        $crawler  = $client->request('GET', '/translations/bar.json');
        $response = $client->getResponse();

        $this->assertEquals(<<<JSON
{
    "fallback": "en",
    "defaultDomain": "messages",
    "translations": {"en":{"bar":{"bye":"bye"}}}
}

JSON
            , $response->getContent());
    }

    public function testGetTranslationsWithThreeLettersLocale()
    {
        $client  = static::createClient();

        $crawler  = $client->request('GET', '/translations/messages.json?locales=ach_UG');
        $response = $client->getResponse();

        $this->assertEquals(<<<JSON
{
    "fallback": "en",
    "defaultDomain": "messages",
    "translations": {"ach_UG":{"messages":{"hello":"hello"}}}
}

JSON
            , $response->getContent());
    }
}

<?php

namespace App\Module\Core\Service;

use NumberFormatter;
use App\Module\Core\Entity\Money;
use App\Module\Core\Enum\Magnum;
use Doctrine\Persistence\Proxy;
use App\Misc\Doctrine\CustomEntityManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CommonService
{

    public function __construct(
        protected ParameterBagInterface $params,
    ) {
    }

    public function getClassName($object, $noTypeText = false)
    {
        $className = str_replace('Proxies\\__CG__\\', '', is_string($object) ? $object : get_class($object));
        $parts = explode('\\', $className);
        $serviceName = $parts[count($parts) - 1];
        if ($noTypeText) {
            return str_replace(
                ['Service', 'Repository', 'Entity', 'Controller'],
                ['', '', '', ''],
                $serviceName
            );
        }
        return $serviceName;
    }

    function toSnakeCase($input)
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    public function cleanInput($inputText)
    {
        return strip_tags($inputText);
    }

    private $plural = array(
        '/(quiz)$/i'                     => "$1zes",
        '/^(ox)$/i'                      => "$1en",
        '/([m|l])ouse$/i'                => "$1ice",
        '/(matr|vert|ind)ix|ex$/i'       => "$1ices",
        '/(x|ch|ss|sh)$/i'               => "$1es",
        '/([^aeiouy]|qu)y$/i'            => "$1ies",
        '/(hive)$/i'                     => "$1s",
        '/(?:([^f])fe|([lr])f)$/i'       => "$1$2ves",
        '/(shea|lea|loa|thie)f$/i'       => "$1ves",
        '/sis$/i'                        => "ses",
        '/([ti])um$/i'                   => "$1a",
        '/(tomat|potat|ech|her|vet)o$/i' => "$1oes",
        '/(bu)s$/i'                      => "$1ses",
        '/(alias)$/i'                    => "$1es",
        '/(octop)us$/i'                  => "$1i",
        '/(ax|test)is$/i'                => "$1es",
        '/(us)$/i'                       => "$1es",
        '/s$/i'                          => "s",
        '/$/'                            => "s"
    );

    private $singular = array(
        '/(quiz)zes$/i'                                                    => "$1",
        '/(matr)ices$/i'                                                   => "$1ix",
        '/(vert|ind)ices$/i'                                               => "$1ex",
        '/^(ox)en$/i'                                                      => "$1",
        '/(alias)es$/i'                                                    => "$1",
        '/(octop|vir)i$/i'                                                 => "$1us",
        '/(cris|ax|test)es$/i'                                             => "$1is",
        '/(shoe)s$/i'                                                      => "$1",
        '/(o)es$/i'                                                        => "$1",
        '/(bus)es$/i'                                                      => "$1",
        '/([m|l])ice$/i'                                                   => "$1ouse",
        '/(x|ch|ss|sh)es$/i'                                               => "$1",
        '/(m)ovies$/i'                                                     => "$1ovie",
        '/(s)eries$/i'                                                     => "$1eries",
        '/([^aeiouy]|qu)ies$/i'                                            => "$1y",
        '/([lr])ves$/i'                                                    => "$1f",
        '/(tive)s$/i'                                                      => "$1",
        '/(hive)s$/i'                                                      => "$1",
        '/(li|wi|kni)ves$/i'                                               => "$1fe",
        '/(shea|loa|lea|thie)ves$/i'                                       => "$1f",
        '/(^analy)ses$/i'                                                  => "$1sis",
        '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => "$1$2sis",
        '/([ti])a$/i'                                                      => "$1um",
        '/(n)ews$/i'                                                       => "$1ews",
        '/(h|bl)ouses$/i'                                                  => "$1ouse",
        '/(corpse)s$/i'                                                    => "$1",
        '/(us)es$/i'                                                       => "$1",
        '/s$/i'                                                            => ""
    );

    private $irregular = array(
        'move'   => 'moves',
        'foot'   => 'feet',
        'goose'  => 'geese',
        'sex'    => 'sexes',
        'child'  => 'children',
        'man'    => 'men',
        'tooth'  => 'teeth',
        'person' => 'people',
        'valve'  => 'valves'
    );

    private $uncountable = array(
        'sheep',
        'fish',
        'deer',
        'series',
        'species',
        'money',
        'rice',
        'information',
        'equipment'
    );

    public function pluralize($string)
    {
        // save some time in the case that singular and plural are the same
        if (in_array(strtolower($string), $this->uncountable))
            return $string;


        // check for irregular singular forms
        foreach ($this->irregular as $pattern => $result) {
            $pattern = '/' . $pattern . '$/i';

            if (preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string);
        }

        // check for matches using regular expressions
        foreach ($this->plural as $pattern => $result) {
            if (preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string);
        }

        return $string;
    }

    public function singularize($string)
    {
        // save some time in the case that singular and plural are the same
        if (in_array(strtolower($string), $this->uncountable))
            return $string;

        // check for irregular plural forms
        foreach ($this->irregular as $result => $pattern) {
            $pattern = '/' . $pattern . '$/i';

            if (preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string);
        }

        // check for matches using regular expressions
        foreach ($this->singular as $pattern => $result) {
            if (preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string);
        }

        return $string;
    }

    function truncateWords($input, $numwords, $padding = "...")
    {
        $output = strtok($input, " \n");
        while (--$numwords > 0) $output .= " " . strtok(" \n");
        if ($output != $input) $output .= $padding;
        return $output;
    }

    function isEntity(CustomEntityManager $em, $class): bool
    {
        if (is_object($class)) {
            $class = ($class instanceof Proxy)
                ? get_parent_class($class)
                : get_class($class);
        }

        return !$em->getMetadataFactory()->isTransient($class);
    }

    function toKebabCase(string $string)
    {
        // Replace repeated spaces to underscore
        $string = preg_replace('/[\s.]+/', '_', $string);
        // Replace un-willing chars to hyphen.
        $string = preg_replace('/[^0-9a-zA-Z_\-]/', '-', $string);
        // Skewer the capital letters
        $string = strtolower(preg_replace('/[A-Z]+/', '-\0', $string));
        $string = trim($string, '-_');

        return preg_replace('/[_\-][_\-]+/', '-', $string);
    }

    /**
     * Convert a value to studly caps case.
     *
     * @param string $value
     * @return string
     */
    public function toPascalCase($value)
    {
        $key = $value;

        $pascalCache = [];

        if (isset($pascalCache[$key])) {
            return $pascalCache[$key];
        }

        $pascalWords = explode(' ', str_replace(['-', '_'], ' ', $value));

        $studlyWords = array_map(fn($word) => ucfirst($word), $pascalWords);

        return $pascalCache[$key] = implode($studlyWords);
    }

    function slugify($string, $replace = [], $delimiter = '-')
    {
        if (!extension_loaded('intl')) {
            throw new \Exception('intl module not loaded');
        }

        if (!extension_loaded('mbstring')) {
            throw new \Exception('mbstring module not loaded');
        }

        // Save the old locale and set the new locale to UTF-8
        $oldLocale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'en_US.UTF-8');

        // Better to replace given $replace array as index => value
        // Example $replace['ı' => 'i', 'İ' => 'i'];
        if (!empty($replace) && is_array($replace)) {
            $string = str_replace(
                array_keys($replace),
                array_values($replace),
                $string
            );
        }

        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');

        $string = $transliterator->transliterate(
            mb_convert_encoding(
                htmlspecialchars_decode($string),
                'UTF-8',
                'auto'
            )
        );

        self::restoreLocale($oldLocale);

        return self::cleanString($string, $delimiter);
    }

    /**
     * Revert back to the old locale
     */
    protected
    static function restoreLocale($oldLocale)
    {
        if ((stripos($oldLocale, '=') > 0)) {
            parse_str(
                str_replace(
                    ';',
                    '&',
                    $oldLocale
                ),
                $loc
            );

            $oldLocale = array_values($loc);
        }

        setlocale(LC_ALL, $oldLocale);
    }

    protected
    static function cleanString($string, $delimiter)
    {

        // replace non letter or non digits by -
        $string = preg_replace('#[^\pL\d]+#u', '-', $string);

        // Trim trailing -
        $string = trim($string, '-');

        $clean = preg_replace('~[^-\w]+~', '', $string);
        $clean = strtolower($clean);
        $clean = preg_replace('#[\/_|+ -]+#', $delimiter, $clean);
        $clean = trim($clean, $delimiter);

        return $clean;
    }

    public function randomString($n) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
    
        for ($i = 0; $i < $n; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }
    
        return $randomString;
    }
    
    public function encrypt($string, $cipher = 'aes-128-gcm') {
        $key = base64_decode($this->params->get('app_salt'));
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt($string, $cipher, $key, $options=0, $iv,$tag);
        return base64_encode($iv.'|||'.$tag.'|||'.$ciphertext);
    }
    public function decrypt($string, $cipher = 'aes-128-gcm') {
        $string = base64_decode($string);
        $key = base64_decode($this->params->get('app_salt'));
        list($iv, $tag, $ciphertext) = explode('|||', $string);
        return openssl_decrypt($ciphertext, $cipher, $key, $options=0, $iv, $tag);
    }
    function traverseDeepArray($haystack, $indexes, $propertyDelimiter = '.') {
        $keys = explode($propertyDelimiter, $indexes);
        foreach ($keys as $level => $key) {
            if(str_starts_with($key, 'Relation')) {
                $key = str_replace('Relation', '', $key);
            }
            if(is_null($haystack) || is_null($indexes)) return null;
            if (!key_exists($key, $haystack)) {
                if($key === 'chargeableWeight' || $key === 'totalUnit' || $key === 'totalWeight') {
                    return null;
                }
                throw new \Exception(
                    sprintf(
                        "Path attempt failed for [%s]. No `%s` key found on levelIndex %d",
                        implode('][', $keys),
                        $key,
                        $level
                    )
                );
            }
            $haystack = $haystack[$key];
        }
        return $haystack;
    }
    public function revertStringToNumber($string) {
        $string = str_replace(' ', '', $string);
        $string = str_replace(',', '', $string);
        return (float) $string;
    }

    public function convertMoney(Money $money, string $toCurrency, float $rate): ?float {
        if ($money->getAmount() === null) return null;
        if($money->getCurrency() === $toCurrency) {
            return $money->getAmount();
        }
        if(empty($money->getRate())) {
            return $money->getAmount();
        }
        if(empty($money->getRate()) || abs($money->getRate() - 0) < PHP_FLOAT_EPSILON ) return $money->getAmount();
        return ($money->getAmount() * $rate ) / $money->getRate();
    }
}

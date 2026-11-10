<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Phrase and word glossary used to seed product name translations (PT → EN/ES).
 *
 * Longer phrases are applied first so "em pó" becomes "powder" before the
 * individual words are considered. Unknown tokens (brands, cultivars, codes)
 * are kept as-is — the admin can refine any result from the products screen.
 */
final class ProductTranslationGlossary
{
    /** @var array<string, array{0: string, 1: string}> phrase => [en, es] */
    private const PHRASES = [
        'em pó' => ['powder', 'en polvo'],
        'em po' => ['powder', 'en polvo'],
        'em flocos' => ['flakes', 'en copos'],
        'em grãos' => ['whole grains', 'en grano'],
        'em graos' => ['whole grains', 'en grano'],
        'em granulado' => ['granulated', 'granulado'],
        'em cubos' => ['cubes', 'en cubos'],
        'em chips' => ['chips', 'en chips'],
        'em fatias' => ['sliced', 'en lonjas'],
        'em fatiado' => ['sliced', 'en lonjas'],
        'em flocos 5x5' => ['flakes 5x5', 'copos 5x5'],
        'em flocos 10x10' => ['flakes 10x10', 'copos 10x10'],
        'sem casca' => ['peeled', 'sin cáscara'],
        'sem. casca' => ['peeled', 'sin cáscara'],
        's/ casca' => ['peeled', 'sin cáscara'],
        'com casca' => ['with peel', 'con cáscara'],
        'sem raiz' => ['without root', 'sin raíz'],
        'com raiz' => ['with root', 'con raíz'],
        'sem amido' => ['starch free', 'sin almidón'],
        'com amido' => ['with starch', 'con almidón'],
        'desidratado' => ['dehydrated', 'deshidratado'],
        'desidratada' => ['dehydrated', 'deshidratado'],
        'desidratados' => ['dehydrated', 'deshidratado'],
        'desidratadas' => ['dehydrated', 'deshidratado'],
        'cristalizado' => ['crystallized', 'cristalizado'],
        'cristalizada' => ['crystallized', 'cristalizado'],
        'defumado' => ['smoked', 'ahumado'],
        'defumada' => ['smoked', 'ahumado'],
        'parboilizado' => ['parboiled', 'parboilizado'],
        'granular' => ['granular', 'granular'],
        'granulado' => ['granulated', 'granulado'],
        'granulada' => ['granulated', 'granulado'],
        'laminado' => ['flaked', 'laminado'],
        'laminada' => ['flaked', 'laminado'],
        'triturado' => ['ground', 'triturado'],
        'triturada' => ['ground', 'triturado'],
        'ralado' => ['grated', 'rallado'],
        'ralada' => ['grated', 'rallado'],
        'fatiado' => ['sliced', 'lonjado'],
        'fatiada' => ['sliced', 'lonjado'],
        'integro' => ['whole', 'entero'],
        'integra' => ['whole', 'entera'],
        'inteiro' => ['whole', 'entero'],
        'inteira' => ['whole', 'entera'],
        'puro' => ['pure', 'puro'],
        'pura' => ['pure', 'pura'],
        'fresco' => ['fresh', 'fresco'],
        'fresca' => ['fresh', 'fresca'],
        'seco' => ['dry', 'seco'],
        'seca' => ['dry', 'seca'],
        'doce' => ['sweet', 'dulce'],
        'salgado' => ['salty', 'salado'],
        'amargo' => ['bitter', 'amargo'],
        'azedo' => ['sour', 'ácido'],
        'frito' => ['fried', 'frito'],
        'frita' => ['fried', 'frito'],
        'tostado' => ['toasted', 'tostado'],
        'tostada' => ['toasted', 'tostada'],
        'cru' => ['raw', 'crudo'],
        'crua' => ['raw', 'cruda'],
        'convencional' => ['conventional', 'convencional'],
        'premium' => ['premium', 'premium'],
        'standard' => ['standard', 'estándar'],
        'natural' => ['natural', 'natural'],
        'naturais' => ['natural', 'natural'],
        'orgânico' => ['organic', 'orgánico'],
        'organico' => ['organic', 'orgánico'],
        'misturado' => ['mixed', 'mezclado'],
        'mistos' => ['mixed', 'mezclado'],
        'misto' => ['mixed', 'mezclado'],
        'extrafino' => ['extra fine', 'extra fino'],
        'grosso' => ['coarse', 'grueso'],
        'fino' => ['fine', 'fino'],
        'media' => ['medium', 'medio'],
        'média' => ['medium', 'medio'],
        'recheio' => ['filling', 'relleno'],
        'revestida' => ['coated', 'recubierto'],
        'revestido' => ['coated', 'recubierto'],
        'grão-de-bico' => ['chickpea', 'garbanzo'],
        'grao-de-bico' => ['chickpea', 'garbanzo'],
        'feno-grego' => ['fenugreek', 'fenogreco'],
        'fenogrego' => ['fenugreek', 'fenogreco'],
        'alho-poró' => ['leek', 'poro'],
        'alho-poro' => ['leek', 'poro'],
        'mostarda amarela' => ['yellow mustard', 'mostaza amarilla'],
        'mostarda marrom' => ['brown mustard', 'mostaza marrón'],
        'sálvia folhas' => ['sage leaves', 'hojas de salvia'],
        'salvia folhas' => ['sage leaves', 'hojas de salvia'],
        'sene folhas' => ['senna leaves', 'hojas de sen'],
        'sálvia cut' => ['sage cut', 'salvia cortada'],
        'salvia cut' => ['sage cut', 'salvia cortada'],
        'caroço' => ['pit', 'hueso'],
        'caroco' => ['pit', 'hueso'],
        'semente' => ['seed', 'semilla'],
        'sementes' => ['seeds', 'semillas'],
        'safra' => ['harvest', 'cosecha'],
        'borda' => ['edge', 'borde'],
        'listrada' => ['striped', 'rayada'],
        'vermelho' => ['red', 'rojo'],
        'verde' => ['green', 'verde'],
        'preto' => ['black', 'negro'],
        'branco' => ['white', 'blanco'],
        'ambar' => ['amber', 'ámbar'],
        'âmbar' => ['amber', 'ámbar'],
        'comercial' => ['commercial', 'comercial'],

    ];

    /** @var array<string, array{0: string, 1: string}> word => [en, es] */
    private const WORDS = [
        'abacaxi' => ['pineapple', 'piña'],
        'abóbora' => ['pumpkin', 'calabaza'],
        'abobora' => ['pumpkin', 'calabaza'],
        'aipo' => ['celery', 'apio'],
        'alho' => ['garlic', 'ajo'],
        'alho-frito' => ['fried garlic', 'ajo frito'],
        'cebola' => ['onion', 'cebolla'],
        'cenoura' => ['carrot', 'zanahoria'],
        'ervilha' => ['pea', 'guisante'],
        'feijão' => ['bean', 'frijol'],
        'alubia' => ['white bean', 'alubia'],
        'tomate' => ['tomato', 'tomate'],
        'morango' => ['strawberry', 'fresa'],
        'macã' => ['apple', 'manzana'],
        'maca' => ['apple', 'manzana'],
        'maçã' => ['apple', 'manzana'],
        'uva' => ['grape', 'uva'],
        'kiwi' => ['kiwi', 'kiwi'],
        'cereja' => ['cherry', 'cereza'],
        'figo' => ['fig', 'higo'],
        'hortelã' => ['peppermint', 'hierbabuena'],
        'manjericão' => ['basil', 'albahaca'],
        'manjericao' => ['basil', 'albahaca'],
        'salsa' => ['parsley', 'perejil'],
        'sultaninas' => ['sultanas', 'pasas sultanas'],
        'sultanas' => ['sultanas', 'pasas sultanas'],
        'girassol' => ['sunflower', 'girasol'],
        'hortela' => ['peppermint', 'hierbabuena'],
        'vegetais' => ['vegetables', 'verduras'],
        'frutas' => ['fruits', 'frutas'],
        'canela' => ['cinnamon', 'canela'],
        'cúrcuma' => ['turmeric', 'cúrcuma'],
        'curcuma' => ['turmeric', 'cúrcuma'],
        'gengibre' => ['ginger', 'jengibre'],
        'páprica' => ['paprika', 'pimentón'],
        'paprica' => ['paprika', 'pimentón'],
        'pimenta' => ['pepper', 'pimienta'],
        'pimenta-do-reino' => ['black pepper', 'pimienta negra'],
        'pimenta do reino' => ['black pepper', 'pimienta negra'],
        'pimenta-reino' => ['black pepper', 'pimienta negra'],
        'quebrada' => ['broken', 'quebrada'],
        'quebrado' => ['broken', 'quebrado'],
        'preta' => ['black', 'negra'],
        'branca' => ['white', 'blanca'],
        'pimentão' => ['bell pepper', 'pimiento'],
        'pimentao' => ['bell pepper', 'pimiento'],
        'cominho' => ['cumin', 'comino'],
        'coentro' => ['coriander', 'cilantro'],
        'endro' => ['dill', 'eneldo'],
        'funcho' => ['fennel', 'hinojo'],
        'fenogrego' => ['fenugreek', 'fenogreco'],
        'cardamomo' => ['cardamom', 'cardamomo'],
        'cravo' => ['clove', 'clavo'],
        'louro' => ['bay leaf', 'hoja de laurel'],
        'noz-moscada' => ['nutmeg', 'nuez moscada'],
        'noz' => ['nut', 'nuez'],
        'moscada' => ['nutmeg', 'nuez moscada'],
        'anis' => ['anise', 'anís'],
        'anis-estrelado' => ['star anise', 'anís estrella'],
        'estrelado' => ['star', 'estrella'],
        'zimbro' => ['juniper', 'enebro'],
        'alecrim' => ['rosemary', 'romero'],
        'alfazema' => ['lavender', 'lavanda'],
        'orégano' => ['oregano', 'orégano'],
        'oregano' => ['oregano', 'orégano'],
        'hibisco' => ['hibiscus', 'hibisco'],
        'flor' => ['flower', 'flor'],
        'calendula' => ['calendula', 'caléndula'],
        'segurella' => ['savory', 'ajedrea'],
        'segurelha' => ['savory', 'ajedrea'],
        'manjerona' => ['marjoram', 'mejorana'],
        'gergelim' => ['sesame', 'ajonjolí'],
        'quinoa' => ['quinoa', 'quinua'],
        'linhaça' => ['flaxseed', 'linaza'],
        'linhaca' => ['flaxseed', 'linaza'],
        'salvia' => ['sage', 'salvia'],
        'tomilho' => ['thyme', 'tomillo'],
        'passas' => ['raisins', 'pasas'],
        'camomila' => ['chamomile', 'manzanilla'],
        'sene' => ['senna', 'sena'],
        'xilitol' => ['xylitol', 'xilitol'],
        'nozes' => ['walnuts', 'nueces'],
        'psyllium' => ['psyllium', 'psyllio'],
        'husk' => ['husk', 'cáscara'],
        'guar' => ['guar', 'guar'],
        'espirulina' => ['spirulina', 'espirulina'],
        'creatina' => ['creatine', 'creatina'],
        'monohidratada' => ['monohydrate', 'monohidratado'],
        'eritritol' => ['erythritol', 'eritritol'],
        'maltitol' => ['maltitol', 'maltitol'],
        'glutamato' => ['glutamate', 'glutamato'],
        'sorbato' => ['sorbate', 'sorbato'],
        'benzoato' => ['benzoate', 'benzoato'],
        'alumínio' => ['aluminum', 'aluminio'],
        'aluminio' => ['aluminum', 'aluminio'],
        'sulfato' => ['sulfate', 'sulfato'],
        'amônia' => ['ammonia', 'amoníaco'],
        'amonia' => ['ammonia', 'amoníaco'],
        'farinha' => ['flour', 'harina'],
        'goma' => ['gum', 'goma'],
        'açúcar' => ['sugar', 'azúcar'],
        'acucar' => ['sugar', 'azúcar'],
        'creme' => ['cream', 'crema'],
        'salgada' => ['salted', 'salada'],
        'calabresa' => ['calabresa sausage', 'salchichón'],
        'azeitona' => ['olive', 'aceituna'],
        'azeitonas' => ['olives', 'aceitunas'],
        'sardinha' => ['sardine', 'sardina'],
        'atum' => ['tuna', 'atún'],
        'camarão' => ['shrimp', 'camarón'],
        'camarao' => ['shrimp', 'camarón'],
        'salmão' => ['salmon', 'salmón'],
        'salmao' => ['salmon', 'salmón'],
        'peixe' => ['fish', 'pescado'],
        'carne' => ['meat', 'carne'],
        'frango' => ['chicken', 'pollo'],
        'porco' => ['pork', 'cerdo'],
        'arroz' => ['rice', 'arroz'],
        'feijao' => ['bean', 'frijol'],
        'milho' => ['corn', 'maíz'],
        'trigo' => ['wheat', 'trigo'],
        'cevada' => ['barley', 'cebada'],
        'aveia' => ['oats', 'avena'],
        'grão' => ['grain', 'grano'],
        'grao' => ['grain', 'grano'],
        'bico' => ['chickpea', 'garbanzo'],
        'lentilha' => ['lentil', 'lenteja'],
        'grao-de-bico' => ['chickpea', 'garbanzo'],
        'pó' => ['powder', 'polvo'],
        'po' => ['powder', 'polvo'],
        'flocos' => ['flakes', 'copos'],
        'chips' => ['chips', 'chips'],
        'cubos' => ['cubes', 'cubos'],
        'sacos' => ['bags', 'bolsas'],
        'caixa' => ['box', 'caja'],
        'caixas' => ['boxes', 'cajas'],
        'pcs' => ['pcs', 'uds'],
        'roll' => ['roll', 'rollo'],
        'mix' => ['mix', 'mezcla'],
        'spray' => ['spray', 'spray'],
        'alta' => ['high', 'alta'],
        'alto' => ['high', 'alto'],
        'baixa' => ['low', 'baja'],
        'baixo' => ['low', 'bajo'],
        'mono' => ['mono', 'mono'],
        'de' => ['of', 'de'],
        'com' => ['with', 'con'],
        'sem' => ['without', 'sin'],
        'e' => ['and', 'y'],
        'ou' => ['or', 'o'],
        'em' => ['in', 'en'],
        'para' => ['for', 'para'],

    ];

    /**
     * @return array{en: string, es: string}
     */
    public static function translate(string $name): array
    {
        $en = self::apply($name, 0);
        $es = self::apply($name, 1);

        return ['en' => $en, 'es' => $es];
    }

    private static function apply(string $name, int $langIndex): string
    {
        $working = $name;

        $phrases = self::PHRASES;
        uksort($phrases, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($phrases as $phrase => $pair) {
            if ($phrase === '' || $pair[$langIndex] === '') {
                continue;
            }
            $working = preg_replace(
                '/\b'.preg_quote($phrase, '/').'\b/iu',
                $pair[$langIndex],
                $working
            ) ?? $working;
        }

        $tokens = preg_split('/(\s+)/u', $working, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            return $working;
        }

        foreach ($tokens as $index => $token) {
            if ($token === '' || preg_match('/^\s+$/u', $token)) {
                continue;
            }

            $lookup = mb_strtolower($token);

            if (isset(self::WORDS[$lookup])) {
                $translated = self::WORDS[$lookup][$langIndex];
                $tokens[$index] = self::matchCase($token, $translated);
            }
        }

        return implode('', $tokens);
    }

    private static function matchCase(string $source, string $translated): string
    {
        if (mb_strlen($source) > 1 && $source === mb_strtoupper($source)) {
            return mb_strtoupper($translated);
        }

        $first = mb_substr($source, 0, 1);
        if ($first !== '' && $first === mb_strtoupper($first) && $first !== mb_strtolower($first)) {
            return ucfirst($translated);
        }

        return $translated;
    }
}

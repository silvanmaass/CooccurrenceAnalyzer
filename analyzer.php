<?php

require 'vendor/autoload.php';

use NlpTools\Tokenizers\WhitespaceTokenizer;
use NlpTools\Classifiers\MultinomialNBClassifier;
use NlpTools\Lemmatizers\Lemmatizer;
use Graphp\GraphViz\GraphViz;
use Fhaculty\Graph\Graph;

class CooccurrenceAnalyzer {

    private $textFile;
    private $stopWords = ['und', 'oder', 'ist', 'die', 'der']; // Füge hier weitere Stoppwörter hinzu
    private $posTagger;
    private $lemmatizer;

    public function __construct($textFile) {
        $this->textFile = $textFile;
        $this->posTagger = new WhitespaceTokenizer();
        $this->lemmatizer = new Lemmatizer(); // Lemmatizer-Instanz hinzufügen
    }

    public function analyzeCooccurrences($windowSize = 2, $includeBigrams = true, $posFilter = []) {
        $text = file_get_contents($this->textFile);

        // Normalisiere den Text und entferne Stoppwörter
        $words = $this->preprocessText($text);

        if ($includeBigrams) {
            $bigrams = $this->generateBigrams($words);
            $words = array_merge($words, $bigrams);
        }

        $cooccurrences = [];

        for ($i = 0; $i < count($words); $i++) {
            $currentWord = $words[$i];
            $posTag = $this->getPartOfSpeech($currentWord);

            // Überspringe Wörter, die nicht der gewünschten Wortart entsprechen
            if ($posFilter && !in_array($posTag, $posFilter)) {
                continue; 
            }

            $start = max(0, $i - $windowSize);
            $end = min(count($words) - 1, $i + $windowSize);

            for ($j = $start; $j <= $end; $j++) {
                $targetWord = $words[$j];

                if ($currentWord !== $targetWord) {
                    $cooccurrence = $currentWord . ' - ' . $targetWord;

                    if (!isset($cooccurrences[$cooccurrence])) {
                        $cooccurrences[$cooccurrence] = 1;
                    } else {
                        $cooccurrences[$cooccurrence]++;
                    }
                }
            }
        }

        arsort($cooccurrences);

        foreach ($cooccurrences as $cooccurrence => $frequency) {
            echo "$cooccurrence: $frequency Mal\n";
        }

        return $cooccurrences;
    }

    private function preprocessText($text) {
        // Kleinschreibung
        $text = strtolower($text);
        
        // Ersetze Satzzeichen durch Leerzeichen
        $text = str_replace(["\r\n", "\r", "\n", ".", ",", "(", ")", "\"", "'", "?", "!", ";", ":"], ' ', $text);

        // Entferne Stoppwörter
        $text = ' ' . $text . ' ';
        foreach ($this->stopWords as $stopWord) {
            $text = str_replace(' ' . $stopWord . ' ', ' ', $text);
        }

        // Normalisiere Wörter
        $words = str_word_count($text, 1);

        // Wende den Lemmatizer an, um die Wörter auf ihre Grundform zu reduzieren
        $lemmatizedWords = array_map([$this->lemmatizer, 'getLemma'], $words);

        return $lemmatizedWords;
    }

    private function generateBigrams($words) {
        $bigrams = [];
        for ($i = 0; $i < count($words) - 1; $i++) {
            $bigram = $words[$i] . ' ' . $words[$i + 1];
            $bigrams[] = $bigram;
        }
        return $bigrams;
    }

    private function getPartOfSpeech($word) {
        // Verwende die WhitespaceTokenizer-Bibliothek für einfaches POS-Tagging
        $tags = $this->posTagger->tag([$word]);
        
        // Das erste Element enthält das POS-Tag
        return reset($tags)[1];
    }

    public function visualizeCooccurrences($cooccurrences) {
        $graph = new Graph();
        $graphviz = new GraphViz();

        foreach ($cooccurrences as $cooccurrence => $frequency) {
            list($word1, $word2) = explode(' - ', $cooccurrence);

            // Erstelle Knoten und Kanten zwischen Wörtern
            $v1 = $graph->createVertex($word1, true);
            $v2 = $graph->createVertex($word2, true);

            $edge = $v1->createEdgeTo($v2);
            $edge->setWeight($frequency);
        }

        // Visualisiere das Netzwerk
        $graphviz->display($graph);
    }
}

// Beispiel für die Verwendung des Tools:

$textFile = "path/to/textfile.txt";

$cooccurrenceAnalyzer = new CooccurrenceAnalyzer($textFile);
$cooccurrences = $cooccurrenceAnalyzer->analyzeCooccurrences(2, true, ['NN']); // Beispiel: Fenstergröße von 2, Berücksichtigung von Bigrammen, nur Substantive
$cooccurrenceAnalyzer->visualizeCooccurrences($cooccurrences);

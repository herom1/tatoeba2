<?php
/**
 *  Tatoeba Project, free collaborative creation of languages corpuses project
 *  Copyright (C) 2015  Gilles Bedel
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

App::import('Model', 'Sentence');
App::import('Model', 'Transcription');

class TranscriptionsShell extends Shell {

    public $uses = array('Sentence', 'Transcription');

    private function detectTranscriptionsFor($sentences) {
        $result = array();
        foreach ($sentences as $sentence) {
            $script = $this->Transcription->detectScript($sentence['Sentence']['lang'], $sentence['Sentence']['text']);
            $result[] = array(
                'id' => $sentence['Sentence']['id'],
                'script' => $script,
            );
        }
        return $result;
    }

    private function autogen($params) {
        $param = array_shift($params);
        $langs = $param ?
                 array($param) :
                 $this->Transcription->transcriptableLanguages();

        foreach ($langs as $lang) {
            echo "=== Proccessing sentences in language '$lang' ===\n";
            echo "Generating new transcriptions";
            $proceeded = $this->allSentencesOperation('_autogen', array(
                'lang' => $lang
            ));
            echo "\n$proceeded transcriptions generated.\n";
        }
    }

    private function _autogen($sentences) {
        $sentenceIds = Set::classicExtract($sentences, '{n}.Sentence.id');
        $this->Transcription->deleteAll(
            array(
                'Transcription.user_id' => null,
                'Transcription.sentence_id' => $sentenceIds,
            ),
            false
        );

        $generated = 0;
        foreach ($sentences as $sentence) {
           $generated += $this->Transcription->generateAndSaveAllTranscriptionsFor($sentence);
        }
        return $generated;
    }

    private function setScript() {
        $proceeded = $this->allSentencesOperation('_setScript', array(
            'lang' => $this->Transcription->langsInNeedOfScriptAutodetection(),
        ));
        echo "\nScript set for $proceeded sentences.\n";
    }

    private function _setScript($sentences) {
        $proceeded = 0;
        $data = $this->detectTranscriptionsFor($sentences);
        $options = array(
            'validate' => true,
            'atomic' => false,
        );
        if ($this->Sentence->saveAll($data, $options))
            $proceeded += count($data);
        return $proceeded;
    }

    private function allSentencesOperation($operation, $conditions) {
        $batchSize = 1000;
        $offset = 0;
        $proceeded = 0;

        do {
            $sentences = $this->Sentence->find('all', array(
                'conditions' => $conditions,
                'fields' => array('id', 'lang', 'text'),
                'contain' => array(),
                'limit' => $batchSize,
                'offset' => $offset,
            ));
            $proceeded += $this->{$operation}($sentences);
            echo ".";
            $offset += $batchSize;
        } while ($sentences);
        return $proceeded;
    }

    private function die_usage() {
        $me = basename(__FILE__, '.php');
        die(
            "\nWrites transcription-related information to the database.\n\n".
            "  Usage: $me {script|autogen [lang]}\n".
            "Example: $me script\n\n".
            "Parameters:\n".
            " script: fills 'script' column in the sentences table.\n".
            "autogen: removes autogenerated transcriptions and regenerate them,\n".
            "         for all the sentences.\n"
        );
    }

    public function main() {
        if (count($this->args) < 1) {
            $this->die_usage();
        }
        $operation = array_shift($this->args);
        switch($operation) {
            case 'script':
                $this->setScript();
                break;
            case 'autogen':
                $this->autogen($this->args);
                break;
            default:
                $this->die_usage();
        }
    }
}

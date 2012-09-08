<?php

/**
 * Youtube
 *
 * @package Amuzi
 * @version 1.0
 * Amuzi - Online music
 * Copyright (C) 2010-2012  Diogo Oliveira de Melo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class Youtube extends DZend_Model
{
    private $_baseUrl = 'https://gdata.youtube.com/feeds/api/videos?';

    public function search($q, $limit = 9, $offset = 1, $complement = array())
    {
        $args = array(
                'q=' . urlencode($q),
                'max-results=' . (int) $limit,
                'start-index=' . (int) $offset
                );

        $xml = file_get_contents($this->_baseUrl . implode('&', $args));
        $xml = str_replace(
            array('<media:', '</media:'), array('<mediaa', '</mediaa'), $xml
        );

        $xmlDoc = new DOMDocument();

        $xmlDoc->loadXML($xml);
        $resultSet = array();
        foreach ($xmlDoc->getElementsByTagName('entry') as $node) {
            $filter = '/http:\/\/gdata.youtube.com\/.*\//';
            foreach ($node->getElementsByTagName('id') as $id)
                $entry['id'] = preg_replace(
                    $filter, '', $id->nodeValue
                );
            foreach ($node->getElementsByTagName('title') as $title)
                $entry['title'] = $title->nodeValue;
            // filtering
            $entry['title'] = str_replace(
                array('"', '\'', '/'),
                array('', '', ''),
                strip_tags($entry['title'])
            );

            foreach ($node->getElementsByTagName('content') as $content)
                $entry['content'] = $content->nodeValue;

            foreach ($node->getElementsByTagName('mediaathumbnail') as $cover)
                $entry['cover'] = $cover->getAttribute('url');

            $mediaContentList = $node->getElementsByTagName('mediaacontent');
            foreach ($mediaContentList as $mediaContent)
                $entry['duration'] = $mediaContent->getAttribute('duration');

            if (!array_key_exists('duration', $entry))
                continue;

            $entry['fid'] = $entry['id'];

            if (!empty($complement)) {
                $entry['artist'] = $complement['artist'];
                $entry['musicTitle'] = $complement['musicTitle'];
            }

            $resultSet[] = new YoutubeEntry($entry);
        }

        return $resultSet;
    }
}

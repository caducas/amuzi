<?php

/**
 * ApiController
 *
 * @version 1.0
 * Amuzi - Online music
 * Copyright (C) 2010-2013  Diogo Oliveira de Melo
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
class ApiController extends DZend_Controller_Action
{
    protected $_error = array(
            'error' => 'Parameter "q" must be specified'
            );

    public function init()
    {
        parent::init();
        $this->_jsonify = true;
    }

    /**
     * searchAction API search call that outputs a list of track objects.
     *
     * @return void
     *
     */
    public function searchAction()
    {
        try {
            $q = $this->_request->getParam('q');
            $list = array();
            if (null !== $q) {
                $limit = $this->_request->getParam('limit', 9);
                $offset = $this->_request->getParam('offset', 1);
                $complement = array();
                $artist = $this->_request->getParam('artist');
                $musicTitle = $this->_request->getParam('musicTitle');
                $complement = null !== $artist && null !== $musicTitle ?
                    array(
                        'artist' => $artist,
                        'musicTitle' => $musicTitle,
                        'artistMusicTitleId' => $this->
                            _artistMusicTitleModel->insert(
                                $artist, $musicTitle
                            )
                    ): array();

                $resultSet = $this->_youtubeModel->search(
                    $q, $limit, $offset, $complement
                );
                if (!empty($complement)) {
                    $list = $this->_trackModel->insertMany(
                        $resultSet, $artist, $musicTitle
                    );
                } else {
                    foreach ($resultSet as $result) {
                        $list[] = $result->getArray();
                    }
                }

                if (!empty($complement)) {
                    $resultSet = $this->_albumModel->search($q);
                    $list = array_merge($list, $resultSet);
                }

                $this->view->output = $list;
            } else {
                $this->view->output = $this->_error;
            }
        } catch(Exception $e) {
            $this->view->exception = $e;
        }
    }

    /**
     * searchsimilarAction API call that searches for similar artist/music and
     * compile a list of music objects.
     *
     * It will output an json array with two elements. The first is the list of 
     * all similar artist/musicTitle and the second is a matrix with the
     * similarity between each of it.
     *;
     * @return void
     */
    public function searchsimilarAction()
    {
        $artistMusicTitleIdList = $this->_request->getParam(
            'artistMusicTitleIdList', array()
        );

        if (($artist = $this->_request->getParam('artist')) != null &&
            ($musicTitle = $this->_request->getParam('musicTitle')) != null) {

            $this->view->output = $this->_musicSimilarityModel
                ->getSimilar($artist, $musicTitle, $artistMusicTitleIdList);
        } elseif (($q = $this->_request->getParam('q')) != null) {
            $item = $this->_artistMusicTitleModel->getBestGuess($q);
            $this->view->output = null === $item ?
                $this->_error :
                $this->_musicSimilarityModel->getSimilar(
                    $item['artist'],
                    $item['musicTitle'],
                    $artistMusicTitleIdList
                );
        } else {
            $this->view->output = $this->_error;
        }
    }

    /**
     * _getAlbum Get full information of the requested album.
     *
     * @param string $artist
     * @param string $album
     * @return array Returns the array conversion of the album row.
     */
    public function _getAlbum($artist, $album)
    {
        $albumRow = null;
        if (($albumRow = $this->_albumModel->get($artist, $album)) === null ||
            count($albumRow->trackList) == 0) {
            $album = $this->_lastfmModel->getAlbum($artist, $album);
            $albumId = $this->_albumModel->insert($album);
            $albumRow = $this->_albumModel->findRowById($albumId);
        }

        $ret = $albumRow->getArray();
        /*
        $trackList = $albumRow->trackList;
        $ret['duration'] = 0;
        $ret['title'] =  $ret['artist'] . ' - ' . $ret['name'];
        foreach ($trackList as $key => $track) {
            if (count($track) === 2) {
                $trackList[$key] = $this->_getMusic($track['artist'], $track['musicTitle']);
            }
            $ret['duration'] += $trackList[$key]['duration'];
            $this->_logger->debug('ApiController::_getAlbum - ' . $trackList[$key]['duration']);
        }

        $ret['trackList'] = $trackList;
        */


        return $ret;
    }

    public function _getMusic($artist, $musicTitle)
    {
        $ret = null;

        $trackRow = $this->_musicTrackLinkModel->getTrack(
            $artist, $musicTitle
        );

        if (null !== $trackRow) {
            $ret = array_merge(
                $trackRow->getArray(),
                array(
                    'artist' => $artist,
                    'musicTitle' => $musicTitle,
                    'artistMusicTitleId' => $this->_artistMusicTitleModel
                        ->insert($artist, $musicTitle)
                )
            );
        }

        return $ret;
    }


    /**
     * searchmusicAction Given the artist and musicTitle parameters, find all
     * necessary information of the given music.
     *
     * @return void
     */
    public function searchmusicAction()
    {
        if (($artist = $this->_request->getParam('artist')) !== null &&
            ($musicTitle = $this->_request->getParam('musicTitle')) !== null) {
            $this->view->output = $this->_getMusic($artist, $musicTitle);
        }
    }

    public function searchalbumAction()
    {
        if (($artist = $this->_request->getParam('artist')) !== null &&
            ($album = $this->_request->getParam('album')) !== null) {
            $this->view->output = $this->_getAlbum($artist, $album);
        }
    }

    /**
     * Given the user's incomplete outputs the list of suggestions in Json.
     *
     * @return void
     */
    public function autocompleteAction()
    {
        $q = strtolower($this->_request->getParam('q'));
        if (null !== $q) {
            // Try to get from music_title and album.
            $list = array();
            $listMusicTitle = $this->_artistMusicTitleModel->autocomplete($q);
            $listAlbum = $this->_albumModel->autocomplete($q);

            $this->_logger->debug("ApiController::autocomplete COUNT A " . count($listMusicTitle) . " " . count($listAlbum));
            if (count($listMusicTitle) < 5) {
                $listMusicTitle = array_merge($listMusicTitle, $this->_amuziSearchModel->autocomplete($q, 'track', 5 - count($listMusicTitle)));
            }
            $this->_taskRequestModel->addTask('SearchString', 'MusicTitle', $q);


            if (count($listAlbum) < 5) {
                $listAlbum = array_merge($listAlbum, $this->_amuziSearchModel->autocomplete($q, 'album', 5 - count($listAlbum)));
            }


            $this->_logger->debug("ApiController::autocomplete COUNT B " . count($listMusicTitle) . " " . count($listAlbum));
            $this->_taskRequestModel->addTask('SearchString', 'Album', $q);

            $list = array_merge($listAlbum, $listMusicTitle);

            $ret = array();
            foreach ($list as $item) {
                $ret[] = $item->getArray();
            }

            $this->view->output = $ret;
        } else {
            $this->view->output = $this->_error;
        }
    }

    /**
     * Given the track id, return information about a specific song.
     *
     * @return void
     */
    public function gettrackAction()
    {
        if (($id = $this->_request->getParam('id')) !== null) {
            $track = $this->_trackModel->findRowById($id);
            if (null !== $track) {
                $this->view->output = $track->toArray();
            } else {
                $this->view->output = null;
            }
        }
    }

    /**
     * Given the playlist id, return information about a specific playlist.
     *
     * @return void
     */
    public function getplaylistAction()
    {
        if (($id = $this->_request->getParam('id')) !== null) {
            $playlist = $this->_playlistModel->findRowById($id);
            // Only return the playlist if it's owned but the user or it is
            // public.
        }
    }

    public function tracksettingsAction()
    {
        $trackId = $this->_request->getParam('track_id');
        $artistMusicTitleId = $this->_request->getParam(
            'artist_music_title_id'
        );
        $trackRow = $this->_trackModel->findRowById($trackId);

        $this->view->trackTitle = $trackRow->title;
        $this->view->trackId = $trackId;
        $this->view->artistMusicTitleId = $artistMusicTitleId;
        $this->view->mp3 = $trackRow->mp3;
        $this->view->flv = $trackRow->flv;
        $this->view->youtubeUrl = $trackRow->youtubeUrl;
        $this->view->facebookUrl = $trackRow->facebookUrl;
        $this->view->shareUrl = $trackRow->shareUrl;
    }

    /**
     * reporterrorAction In case of JS error, the default procedure must be
     * call this action to report it.
     *
     * @return void
     */
    public function reporterrorAction()
    {
        $message = array('Wrong parameters/method', 'error');
        if ($this->_request->isPost()) {
            $origin = $this->_request->getParam('origin');
            $err = $this->_request->getParam('err');
            $obj = $this->_request->getParam('obj');
            $this->_logger->err(
                "ApiController::reporterrorAction -> origin: $origin. "
                . "err: $err. obj: $obj"
            );
            $message = array('Error reported successfully', 'success');
        }

        $this->view->message = $message;
    }

    /**
     * gettopAction Get top music on lastfm.
     *
     * @return void
     */
    public function gettopAction()
    {
        $resultSet = $this->_lastfmModel->getTop();
        $ret = array();
        foreach ($resultSet as $row) {
            $track = $this->_getMusic($row->artist, $row->musicTitle);
            $track['cover'] = $row->cover;
            $ret[] = $track;
        }

        $this->view->resultSet = $ret;
    }
}

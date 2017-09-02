<?php

/*
 *
 * poggit
 *
 * Copyright (C) 2017 SOFe
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace poggit\ci\api;

use poggit\account\Session;
use poggit\ci\builder\ProjectBuilder;
use poggit\ci\lint\V2BuildStatus;
use poggit\module\AjaxModule;
use poggit\resource\ResourceManager;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;
use poggit\utils\OutputManager;

class BuildDataRequestAjax extends AjaxModule {
    public function getName(): string {
        return "ci.build.request";
    }

    protected function impl() {
        header("Content-Type: application/json");
        $projectId = (int) $this->param("projectId");
        $class = $this->param("class");
        $internal = $this->param("internal");

        $isPr = $class === ProjectBuilder::BUILD_CLASS_PR;
        $branchColumn = $isPr ? "substring_index(substring_index(cause, '\"prNumber\":',-1), ',', 1)" : "branch";
        $rows = array_map(function(array $input) use ($isPr) {
            $row = (object) $input;
            $row->repoId = (int) $row->repoId;
            $row->cause = json_decode($row->cause);
            $row->date = (int) $row->date;
            if($isPr) $row->branch = (int) $row->branch;
            $row->virions = [];
            $row->lintCount = (int) ($row->lintCount ?? 0);
            $row->worstLint = (int) ($row->worstLint ?? 0);
            if($row->libs !== null) {
                foreach(explode(",", $row->libs ?? "") as $lib) {
                    $versions = explode(":", $lib, 2);
                    $row->virions[$versions[0]] = $versions[1];
                }
            }
            $row->dlSize = filesize(ResourceManager::pathTo($row->resourceId, "phar"));
            unset($row->libs);
            return $row;
        }, Mysql::query("SELECT (SELECT repoId FROM projects WHERE projects.projectId = builds.projectId) repoId, cause,
                builds.buildId, class, internal, UNIX_TIMESTAMP(created) date, resourceId, $branchColumn branch, sha, main, path,
                bs.cnt lintCount, bs.maxLevel worstLint,
                virion_builds.version virionVersion, virion_builds.api virionApi,
                (SELECT GROUP_CONCAT(CONCAT(vp.name, ':', vvb.version) SEPARATOR ',') FROM virion_usages
                    INNER JOIN builds vb ON virion_usages.virionBuild = vb.buildId
                    INNER JOIN virion_builds vvb ON vvb.buildId = vb.buildId
                    INNER JOIN projects vp ON vp.projectId = vb.projectId
                    WHERE virion_usages.userBuild = builds.buildId) libs
            FROM builds
                LEFT JOIN (SELECT buildId, COUNT(*) cnt, MAX(level) maxLevel FROM builds_statuses GROUP BY buildId) bs
                    ON bs.buildId = builds.buildId
                LEFT JOIN virion_builds ON builds.buildId = virion_builds.buildId
            WHERE projectId = ? AND class = ? AND internal = ?", "iii", $projectId, $class, $internal));

        if(count($rows) === 0) $this->errorNotFound(true);
        $build = $rows[0];
        if(!Curl::testPermission($build->repoId, Session::getInstance()->getAccessToken(true), Session::getInstance()->getName(), "pull")) {
            $this->errorNotFound(true);
        }
        $build->statuses = array_map(function(array $row) {
            $om = OutputManager::$tail->startChild();
            $status = V2BuildStatus::unserializeNew(json_decode($row["body"]), $row["class"], (int) $row["level"]);
            $status->echoHtml();
            return ["level" => $status->level, "class" => $row["class"], "html" => $om->terminateGet()];
        }, Mysql::query("SELECT level, class, body FROM builds_statuses WHERE buildId = ?", "i", $build->buildId));
        echo json_encode($build);
    }

    protected function needLogin(): bool {
        return false;
    }
}

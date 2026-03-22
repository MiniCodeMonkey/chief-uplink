<?php

namespace App\Enums;

enum CommandType: string
{
    case PrdCreate = 'cmd.prd.create';
    case PrdMessage = 'cmd.prd.message';
    case PrdUpdate = 'cmd.prd.update';
    case PrdDelete = 'cmd.prd.delete';
    case RunStart = 'cmd.run.start';
    case RunStop = 'cmd.run.stop';
    case ProjectClone = 'cmd.project.clone';
    case DiffsGet = 'cmd.diffs.get';
    case LogGet = 'cmd.log.get';
    case FilesList = 'cmd.files.list';
    case FileGet = 'cmd.file.get';
    case SettingsGet = 'cmd.settings.get';
    case SettingsUpdate = 'cmd.settings.update';
}

<?php

/**
 * Nextcloud - nextant
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@pontapreta.net>
 * @copyright Maxence Lange 2016
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Nextant\Command;

use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Check extends Base
{

    private $configService;

    private $solrService;

    private $solrTools;

    private $solrAdmin;

    private $settingsController;

    public function __construct($configService, $solrService, $solrTools, $solrAdmin, $settingsController)
    {
        parent::__construct();
        $this->configService = $configService;
        $this->solrService = $solrService;
        $this->solrTools = $solrTools;
        $this->solrAdmin = $solrAdmin;
        $this->settingsController = $settingsController;
    }

    protected function configure()
    {
        parent::configure();
        $this->setName('nextant:check')
            ->setDescription('check, fix and optimise your current Solr configuration')
            ->addOption('disable', null, InputOption::VALUE_NONE, 'disable Nextant')
            ->addOption('info', null, InputOption::VALUE_NONE, 'display some info')
            ->addOption('infoall', null, InputOption::VALUE_NONE, 'display some info (also sensitive)')
            ->addOption('fix', 'f', InputOption::VALUE_NONE, 'fix');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! $this->solrService->configured(true)) {
            $output->writeln('Nextant is not yet configured');
            return;
        }
        
        if ($input->getOption('disable')) {
            $this->configService->setAppValue('configured', '2');
            return;
        }
        
        if ($input->getOption('info') || $input->getOption('infoall')) {
            $info = $this->settingsController->updateSubOptions(true, 'check');
            $info['cloud_version'] = $this->configService->getCloudVersion(true);
            
            if (! $input->getOption('infoall')) {
                $info['solr_url'] = '**HIDDEN**';
                $info['solr_core'] = '**HIDDEN**';
                
                // $qk = $info['index_live_queuekey'];
                // $info['index_live_queuekey'] = substr($qk, 0, - 3) . '???';
            }
            
            $output->writeln(var_export($info, true));
        }
        
        $this->solrService->setOutput($output);
        
        $client = $this->solrService->getClientConfig()['endpoint']['localhost'];
        $output->write('Pinging ' . $client['host'] . ':' . $client['port'] . $client['path'] . $client['core'] . ' : ');
        if ($this->solrAdmin->ping())
            $output->writeln('<info>ok</info>');
        else {
            $output->writeln('<error>fail</error>');
            return false;
        }
        
        if (! $this->solrAdmin->checkSchema(($input->getOption('fix')), $ierror)) {
            if ($ierror->getCode() > 0) {
                $this->output->writeln('*** Error #' . $ierror->getCode() . ' (' . $ierror->getMessage() . ')');
                return;
            }
        }
        
        $output->writeln('');
        $output->writeln('Your solr contains ' . $this->solrTools->count() . ' documents :');
        $output->writeln(' - ' . $this->solrTools->count('files') . ' files');
        $output->writeln(' - ' . $this->solrTools->count('bookmarks') . ' bookmarks');
        
        $output->writeln(' - ' . $this->solrTools->getInfoCore()->index->segmentCount . ' segments');
    }
}




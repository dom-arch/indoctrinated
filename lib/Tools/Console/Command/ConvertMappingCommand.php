<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Indoctrinated\Tools\Console\Command;

use Doctrine\ORM\Tools\Console\MetadataFilter;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;
use Indoctrinated\Tools\EntityGenerator;

/**
 * Command to convert your mapping information between the various formats.
 *
 * @link    www.doctrine-project.org
 * @since   2.0
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class ConvertMappingCommand
    extends \Doctrine\ORM\Tools\Console\Command\ConvertMappingCommand
{
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getHelper('em')->getEntityManager();

        if ($input->getOption('from-database') === true) {
            $databaseDriver = new DatabaseDriver(
                $em->getConnection()->getSchemaManager()
            );

            $em->getConfiguration()->setMetadataDriverImpl(
                $databaseDriver
            );

            if (($namespace = $input->getOption('namespace')) !== null) {
                $databaseDriver->setNamespace($namespace);
            }
        }

        $cmf = new DisconnectedClassMetadataFactory();
        $cmf->setEntityManager($em);
        $metadata = $cmf->getAllMetadata();
        $metadata = MetadataFilter::filter($metadata, $input->getOption('filter'));

        $destPath = $input->getArgument('dest-path');

        $paths = [
            'destPath' => $destPath,
            'traitsPath' => $destPath . '/Traits',
            'validatorsPath' => $destPath . '/Validators'
        ];

        foreach ($paths as $name => $path) {
            // Process destination directory
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }

            $$name = realpath($path);

            if ( ! file_exists($$name)) {
                throw new \InvalidArgumentException(
                    sprintf("Mapping destination directory '<info>%s</info>' does not exist.", $$name)
                );
            }

            if ( ! is_writable($$name)) {
                throw new \InvalidArgumentException(
                    sprintf("Mapping destination directory '<info>%s</info>' does not have write permissions.", $$name)
                );
            }
        }

        $toType = strtolower($input->getArgument('to-type'));

        $exporter = $this->getExporter($toType, $destPath);
        $exporter->setOverwriteExistingFiles($input->getOption('force'));

        if ($toType == 'annotation') {
            $entityGenerator = new EntityGenerator();
            $exporter->setEntityGenerator($entityGenerator);

            $entityGenerator->setNumSpaces($input->getOption('num-spaces'));

            if (($extend = $input->getOption('extend')) !== null) {
                $entityGenerator->setClassToExtend($extend);
            }
        }

        if (count($metadata)) {
            foreach ($metadata as $class) {
                $class->name = $class->table['name'];
                $traitPath = $traitsPath . '/' . $class->name . '.php';
                $validatorPath = $validatorsPath . '/' . $class->name . '.php';

                if (isset($entityGenerator)) {
                    if (!is_file($traitPath)) {
                        file_put_contents($traitPath, $entityGenerator->generateEntityTrait($class));
                    }

                    if (!is_file($validatorPath)) {
                        file_put_contents($validatorPath, $entityGenerator->generateEntityValidator($class));
                    }
                }

                $output->writeln(sprintf('Processing entity "<info>%s</info>"', $class->name));
            }

            $exporter->setMetadata($metadata);
            $exporter->export();

            $output->writeln(PHP_EOL . sprintf(
                    'Exporting "<info>%s</info>" mapping information to "<info>%s</info>"', $toType, $destPath
                ));
        } else {
            $output->writeln('No Metadata Classes to process.');
        }
    }
}
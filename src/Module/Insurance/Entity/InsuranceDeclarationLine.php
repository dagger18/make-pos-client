<?php
namespace App\Module\Insurance\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'insurance_declaration_line')]
class InsuranceDeclarationLine
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private InsuranceDeclaration $declaration;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private InsuranceCertificate $certificate;

    public function getDeclaration(): InsuranceDeclaration { return $this->declaration; }
    public function setDeclaration(InsuranceDeclaration $v): static { $this->declaration = $v; return $this; }
    public function getCertificate(): InsuranceCertificate { return $this->certificate; }
    public function setCertificate(InsuranceCertificate $v): static { $this->certificate = $v; return $this; }
}

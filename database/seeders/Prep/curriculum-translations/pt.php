<?php

// Portuguese (pt) translations for CurriculumSeeder. Keys mirror the English source.
return [
    'modules' => [
        'intro' => [
            'title' => 'O que é soberania digital?',
            'description' => '<p>Em resumo: o direito de uma comunidade, e não de uma empresa distante, de decidir a quem pertencem os seus dados, ferramentas e sistemas digitais e como são governados e utilizados. Estes recursos apresentam a ideia antes de começar a escolher ferramentas.</p>',
        ],
        'digital-landscape' => [
            'title' => 'Compreender o Panorama Digital',
            'goal' => 'Ajudar os participantes a construir uma imagem partilhada e honesta do panorama digital em que a sua comunidade já opera, antes de introduzir qualquer nova tecnologia.',
            'description' => '<p>Construa uma visão comum do terreno digital em que a sua comunidade já se encontra: conectividade, dispositivos, literacia digital e quem controla o quê.</p>',
            'learning_outcomes' => "Descrever a conectividade, os dispositivos e as competências digitais com que a sua comunidade trabalha\nIdentificar quem controla os sistemas digitais de que a sua comunidade depende atualmente\nReconhecer onde os sistemas híbridos (papel e digital) fazem sentido",
        ],
        'knowledge-justice' => [
            'title' => 'Compreender Conhecimento, Justiça e Direitos sobre os Dados',
            'goal' => 'Ajudar os participantes a reconhecer que conhecimentos e que direitos sobre os dados estão em jogo nos sistemas digitais, e a aplicar uma abordagem baseada em direitos ao seu próprio contexto.',
            'description' => '<p>Pergunte de quem é o conhecimento que conta, a quem pertencem os dados que uma comunidade produz e que direitos protegem ambos.</p>',
            'learning_outcomes' => "Explicar de quem é o conhecimento que conta nos sistemas digitais e quem se beneficia dele\nDescrever os direitos que uma comunidade detém sobre os dados que produz\nAplicar uma abordagem baseada em direitos (como os princípios CARE) aos dados comunitários",
        ],
        'community-needs' => [
            'title' => 'Compreender as Necessidades da Comunidade',
            'goal' => 'Ajudar os participantes a compreender melhor as necessidades de dados e ferramentas digitais da sua comunidade ou organização antes de tomar decisões tecnológicas.',
            'description' => '<p>Este módulo oferece um quadro prático para aplicar os conceitos introduzidos nos módulos 1 e 2 ao seu próprio contexto. Centra-se em contextos comunitários e organizacionais, reconhecendo que muitas das abordagens também se aplicam a nível individual, interorganizacional e de sistemas mais amplos. Através de exercícios práticos e exemplos reais, aprenderá a compreender o seu contexto operacional, identificar necessidades e restrições reais, e tomar decisões informadas sobre dados e ferramentas digitais antes de escolher uma tecnologia.</p>',
            // One entry per outcome, in the same order as the English source; in_practice may be omitted.
            'learning_outcomes' => [
                [
                    'statement' => 'Realizar um diagnóstico ao nível do sistema do seu contexto organizacional e operacional.',
                    'in_practice' => 'irá preencher um Canvas de diagnóstico do contexto e escrever uma breve declaração de diagnóstico sobre a sua própria organização.',
                ],
                [
                    'statement' => 'Definir as suas necessidades com base em restrições operacionais reais, e não em soluções presumidas.',
                    'in_practice' => 'irá aplicar o método Problema → Restrição → Necessidade e construir uma primeira Matriz de necessidades.',
                ],
                [
                    'statement' => 'Compreender a necessidade de avaliar e selecionar ferramentas com base em critérios de soberania, incluindo controlo, acessibilidade e sustentabilidade.',
                    'in_practice' => 'será capaz de nomear estes critérios e explicar porque importam; a avaliação detalhada ferramenta a ferramenta é tratada no módulo 4.',
                ],
                [
                    'statement' => 'Explicar o que é necessário para abordar a governação de dados a partir de uma perspetiva baseada em necessidades, incluindo propriedade, acesso e proteção.',
                    'in_practice' => 'será capaz de explicar as quatro componentes de um quadro de governação de dados e esboçar um para a sua própria organização.',
                ],
            ],
            'sessions' => [
                'understanding-your-operational-context' => [
                    'title' => 'Compreender o seu contexto operacional',
                    'summary' => 'Realizar um diagnóstico ao nível do sistema do contexto organizacional e operacional',
                ],
                'defining-what-you-actually-need' => [
                    'title' => 'Definir o que realmente precisa',
                    'summary' => 'Necessidades a partir de restrições, não de soluções presumidas',
                ],
                'deciding-what-belongs-in-a-digital-system' => [
                    'title' => 'Decidir o que pertence a um sistema digital',
                    'summary' => 'Critérios de soberania para avaliar ferramentas',
                ],
                'why-data-governance-starts-with-your-needs' => [
                    'title' => 'Porque a governação de dados começa pelas suas necessidades',
                    'summary' => 'Governação de dados baseada em necessidades',
                ],
            ],
        ],
        'tech-assessment' => [
            'title' => 'Avaliação de Tecnologias',
            'goal' => 'Ajudar os participantes a avaliar ferramentas digitais quanto a propriedade, abertura, funcionamento offline, custo e risco de dependência antes de se comprometerem com uma plataforma.',
            'description' => '<p>Pese as opções em termos de propriedade, abertura, funcionamento offline, custo e risco de dependência (lock-in) antes de se comprometer com uma única plataforma.</p>',
            'learning_outcomes' => "Decidir o que deve ser digitalizado, o que não deve e quem decide\nAvaliar ferramentas em termos de propriedade, abertura, funcionamento offline e custo\nIdentificar riscos de dependência (lock-in) antes de se comprometer com uma plataforma",
        ],
        'tech-strategy' => [
            'title' => 'Estratégia Tecnológica',
            'goal' => 'Ajudar os participantes a transformar o que aprenderam numa única estratégia de soberania digital, concreta e aplicável, para a sua própria comunidade.',
            'description' => '<p>Defina as regras sobre a quem pertencem os dados e como são compartilhados, conecte as ferramentas a um acesso real ao mercado e reúna tudo em uma única estratégia local.</p>',
            'learning_outcomes' => "Definir regras sobre a propriedade, o acesso e o armazenamento dos dados da sua comunidade\nConectar ferramentas digitais a um acesso real ao mercado sem perder valor para intermediários\nReunir todas as etapas em uma única estratégia local de soberania digital",
        ],
        'farm-hack-box' => [
            'title' => 'Comece com a Farm Hack Box',
            'description' => '<p>Uma caixa para hospedar localmente as suas ferramentas digitais soberanas. A Farm Hack Box é hardware construído pela comunidade: um pequeno servidor local que a sua comunidade possui e opera, hospedando as suas próprias ferramentas e dados (armazenamento de arquivos, comunicação, registos agrícolas), mesmo sem internet confiável.</p>',
            'note' => 'Esta etapa é totalmente opcional. Todo o resto no currículo e no kit de ferramentas funciona com ou sem a caixa.',
            'learning_outcomes' => "Explicar o que é a Farm Hack Box e o que ela pode hospedar\nAvaliar se a hospedagem local se adequa às necessidades e à capacidade da sua comunidade\nIdentificar o que seria necessário para montar uma",
        ],
        'knowledge' => [
            'title' => 'Conhecimento',
            'subtitle' => 'Repositórios de Dados e Conhecimento',
            'description' => '<p>Reúna, armazene e preserve os seus próprios registos e o conhecimento da comunidade.</p>',
        ],
        'collaboration' => [
            'title' => 'Colaboração',
            'subtitle' => 'Coordenação Social',
            'description' => '<p>Converse, organize-se e tome decisões em conjunto sem entregar a conversa às Big Tech.</p>',
        ],
        'farm' => [
            'title' => 'Agricultura',
            'subtitle' => 'Gestão Agrícola',
            'description' => '<p>Faça a gestão dos registos agrícolas do dia a dia e do hardware nos seus próprios termos.</p>',
        ],
        'market' => [
            'title' => 'Mercado',
            'subtitle' => 'Acesso ao Mercado',
            'description' => '<p>Chegue aos compradores e faça as suas vendas sem perder valor para uma plataforma intermediária.</p>',
        ],
    ],
    'glossary' => [
        'Big data' => [
            'term' => 'Big data',
            'definition' => 'Big data refere-se a enormes volumes de dados estruturados, semiestruturados e não estruturados, coletados por governos, empresas e organizações, que podem ser explorados para extrair informação valiosa.',
        ],
        'Community sovereignty' => [
            'term' => 'Soberania comunitária',
            'definition' => 'Soberania comunitária refere-se à autonomia e ao autogoverno político de uma comunidade. Este conceito baseia-se no princípio de que cada comunidade é livre para determinar o seu próprio destino e as suas relações com outras comunidades. As políticas de dados devem respeitar a soberania das comunidades para recusar, restringir ou permanecer desconectadas da coleta de dados.',
        ],
        'Data' => [
            'term' => 'Dados',
            'definition' => "Qualquer conjunto de símbolos codificados que representam unidades de informação sobre aspectos específicos do mundo e que podem ser capturados ou gerados, registados, armazenados e transmitidos em forma analógica ou digital.\n\nDa perspectiva dos agricultores, os dados não são um recurso individual que se possa possuir ou controlar isoladamente. Estão incorporados nas suas relações — com os seus territórios agroecológicos, os recursos compartilhados, as comunidades e os governos — e emergem das perguntas, limitações e desafios que enfrentam no dia a dia. A justiça de dados, portanto, não pode ser universal nem abstrata; deve estar enraizada nessas relações vividas. https://agroecologynow.net/what-does-data-justice-mean-for-african-small-holder-farmers-towards-envisioning-a-human-rights-based-approach-in-africa/",
        ],
        'Data extraction' => [
            'term' => 'Extração de dados',
            'definition' => 'A extração de dados é o processo de obter dados a partir de fontes de dados para posterior processamento ou armazenamento. É frequentemente usada para migrar dados para um novo sistema, integrar dados de várias fontes ou analisar dados.',
        ],
        'Data for FSN' => [
            'term' => 'Dados para FSN',
            'definition' => 'Dados para a Segurança Alimentar e Nutricional (FSN) referem-se à informação coletada e analisada para conceber e avaliar políticas eficazes que garantam a segurança alimentar e nutricional.',
        ],
        'Data governance' => [
            'term' => 'Governança de dados',
            'definition' => "Um regime político e económico que estabelece limites à coleta de dados, defendendo os direitos humanos e proibindo qualquer forma de processamento de dados que viole a autonomia ou a autodeterminação individual e coletiva. Só uma governança de dados para a segurança alimentar e nutricional baseada em uma abordagem de direitos humanos garantirá a disponibilidade de dados qualitativos e quantitativos de alta qualidade, oportunos e relevantes, que melhorem a segurança alimentar e nutricional e contribuam para a realização progressiva do direito a uma alimentação saudável e sustentável. Pequenos produtores de alimentos em todo o mundo defendem novas abordagens à governança de dados que protejam não só a sua privacidade, mas também a sua soberania e autonomia.\n\nVer também: https://digifoodproject.org/what-is-data-governance",
        ],
        'Data infrastructures' => [
            'term' => 'Infraestruturas de dados',
            'definition' => 'Infraestruturas de dados referem-se às estruturas e serviços de base necessários para a coleta, o processamento, o armazenamento e a distribuição de dados. Isso inclui hardware, software, redes e instalações usadas para desenvolver, testar, operar, acompanhar, administrar e dar suporte a aplicações de dados.',
        ],
        'Data justice' => [
            'term' => 'Justiça de dados',
            'definition' => 'Justiça de dados refere-se à distribuição equitativa dos benefícios e dos encargos ligados aos dados. É um conceito que usa ideias de justiça social para abordar direitos, equidade e proteções no contexto da dataficação.',
        ],
        'Data literacy' => [
            'term' => 'Literacia de dados',
            'definition' => 'Literacia de dados é a capacidade de ler, compreender, criar e comunicar dados como informação. Inclui as competências essenciais para analisar criticamente, interpretar e usar dados de forma eficaz.',
        ],
        'Data privacy' => [
            'term' => 'Privacidade de dados',
            'definition' => 'A privacidade de dados, também conhecida como privacidade da informação, envolve o tratamento e a proteção de dados sensíveis contra acesso, uso, divulgação, perturbação, modificação ou destruição não autorizados. É um aspecto fundamental da governança de dados que garante a confidencialidade e a privacidade dos dados pessoais.',
        ],
        'Data sovereignty' => [
            'term' => 'Soberania de dados',
            'definition' => 'A capacidade dos diversos atores dos sistemas alimentares de controlar os dados coletados nas suas atividades e de tomar decisões autónomas sobre eles.',
        ],
        'Datafication' => [
            'term' => 'Dataficação',
            'definition' => 'Com o surgimento de tecnologias baseadas em dados, como o melhoramento de plantas assistido por IA, as plataformas agrícolas digitais e a venda de alimentos online, são geradas imensas quantidades de dados, dando origem ao que se conhece como \'dataficação\'. Este processo abrange uma gama diversa de atividades, finalidades, organismos, comunidades e aplicações ao longo de toda a cadeia de abastecimento alimentar. No entanto, a questão de quem se beneficia desta transformação e de como ela responde às desigualdades existentes que levam à perpetuação da insegurança alimentar depende, em grande medida, da governança desses dados.',
        ],
        'Digital food chain' => [
            'term' => 'Cadeia alimentar digital',
            'definition' => 'A aplicação de tecnologias digitais e da dataficação nos sistemas agrícolas e alimentares, transformando os processos tradicionais de produção, distribuição e consumo de alimentos em processos orientados por dados.',
        ],
        'Digital grocery' => [
            'term' => 'Mercearia digital',
            'definition' => 'Mercearia digital refere-se a plataformas online onde os clientes podem comprar alimentos e outros produtos de mercearia e recebê-los à porta de casa.',
        ],
        'Digital technologies' => [
            'term' => 'Tecnologias digitais',
            'definition' => 'Ferramentas e métodos que usam informação digital para resolver problemas, comunicar e criar produtos.',
        ],
        'Digitalization of food systems' => [
            'term' => 'Digitalização dos sistemas alimentares',
            'definition' => 'A digitalização dos sistemas alimentares envolve a aplicação de tecnologias digitais e da dataficação no setor agrícola, transformando os processos tradicionais de produção, distribuição e consumo de alimentos em processos orientados por dados.',
        ],
        'FAIR and CARE principles' => [
            'term' => 'Princípios FAIR e CARE',
            'definition' => 'Os princípios FAIR e CARE são orientações para a gestão e a custódia de dados: FAIR significa Findable, Accessible, Interoperable e Reusable (localizáveis, acessíveis, interoperáveis e reutilizáveis), enquanto CARE significa Collective benefit, Authority to control, Responsibility e Ethics (benefício coletivo, autoridade para controlar, responsabilidade e ética).',
        ],
        'False climate solutions' => [
            'term' => 'Falsas soluções climáticas',
            'definition' => 'Medidas ou tecnologias que afirmam combater as mudanças climáticas, mas que não reduzem as emissões de gases com efeito de estufa na fonte ou que criam outros danos sociais e ambientais.',
        ],
        'Free, Prior, Informed Consent (FPIC)' => [
            'term' => 'Consentimento Livre, Prévio e Informado (CLPI/FPIC)',
            'definition' => 'O Consentimento Livre, Prévio e Informado (CLPI/FPIC) é um princípio que procura proteger os direitos dos Povos Indígenas e das comunidades locais nos processos de tomada de decisão, em particular em relação às suas terras, territórios e recursos.',
        ],
        'Hyper-nudging' => [
            'term' => 'Hyper-nudging',
            'definition' => 'Hyper-nudging é um conceito que envolve o uso de big data e de aprendizagem automática (machine learning) para fornecer estímulos (nudges) altamente personalizados, influenciando o comportamento individual em tempo real.',
        ],
        'Internet of Things' => [
            'term' => 'Internet das Coisas',
            'definition' => 'A rede de objetos físicos — as "coisas" — equipados com sensores, software e outras tecnologias com o objetivo de se conectarem e trocarem dados com outros dispositivos e sistemas através da internet.',
        ],
    ],
];

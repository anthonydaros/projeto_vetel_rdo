CREATE SCHEMA `formulario_bd` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;

use formulario_bd;

SET foreign_key_checks = 0;
truncate table empresa;
truncate table funcionario;
truncate table funcionario_diario_obra;
truncate table diario_obra;
truncate table obra;
SET foreign_key_checks = 1;


create table empresa(
	`id_empresa` int primary key auto_increment,
    `nome_fantasia` varchar(50) unique not null,
    `contratante_sn` tinyint default 1,
    `url_logo` varchar(200) unique
);

create table obra(
	`id_obra` int primary key auto_increment,
    `fk_id_contratante` int not null,
    `fk_id_contratada` int not null,
    `descricao_resumo` varchar(500)
);

create table funcionario(
	`id_funcionario` int primary key auto_increment,
    `fk_id_empresa` int,
    `nome` varchar(250),
    `cargo` varchar(50) not null
);

create table diario_obra(
    `id_diario_obra` int primary key auto_increment,
	`numero_diario` int not null,
    `fk_id_obra` int not null, 
    `data` date not null,
    `obs_gerais` text,
    `horario_trabalho` varchar(50),
    `carga_horas_dia` float(10, 2),
    `total_horas` float(10, 2)
);

create table imagem(
    `id_imagem` int primary key auto_increment,
	`fk_id_diario_obra` int not null,
    `url` varchar(300) unique
);

alter table imagem add constraint fk_imagem_diario_obra
foreign key (fk_id_diario_obra) references diario_obra(id_diario_obra) on delete cascade; 

create table funcionario_diario_ora(
    `id_funcionario_diario_obra` int primary key auto_increment,
	`fk_id_funcionario` int not null,
    `fk_id_diario_obra` int not null,
    `data` date,
    `horario_trabalho` varchar(50),
    `horas_trabalhadas` float(10, 2)
);

create table servico(
	`id_servico` int primary key auto_increment,
    `fk_id_diario_obra` int not null,
    `descricao` varchar(500)
);

alter table funcionario
drop constraint fk_funcionario_empresa;


alter table funcionario add constraint fk_funcionario_empresa
foreign key (fk_id_empresa) references empresa(id_empresa) on delete cascade;

alter table obra
drop constraint fk_obra_contratante;

alter table obra add constraint fk_obra_contratante
foreign key (fk_id_contratante) references empresa(id_empresa) on delete cascade; 

alter table obra
drop constraint fk_obra_contratada;

alter table obra add constraint fk_obra_contratada
foreign key (fk_id_contratada) references empresa(id_empresa) on delete cascade;

alter table diario_obra
drop constraint fk_diario_obra_obra;

alter table diario_obra add constraint fk_diario_obra_obra
foreign key (fk_id_obra) references obra(id_obra) on delete cascade;

alter table funcionario_diario_obra
drop constraint fk_funcionario_diario_obra_funcionario;

alter table funcionario_diario_obra add constraint fk_funcionario_diario_obra_funcionario
foreign key (fk_id_funcionario) references funcionario(id_funcionario) on delete cascade;

alter table funcionario_diario_obra
drop constraint fk_funcionario_diario_obra_diario_obra;

alter table funcionario_diario_obra add constraint fk_funcionario_diario_obra_diario_obra
foreign key (fk_id_diario_obra) references diario_obra(id_diario_obra) on delete cascade;

alter table servico add constraint fk_servico_diario_obra
foreign key (fk_id_diario_obra) references diario_obra(id_diario_obra) on delete cascade;


-------------------------------------------------------------------------------------
